<?php
// ============================================================
// BIDDING ENGINE
// Core bid logic, proxy bidding, anti-sniping mechanics
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';

/**
 * Get current item state with bid information
 * @param int $item_id Item ID
 * @return array|false Item data or false if not found
 */
function getItemState($item_id) {
    return dbGetRow(
        "SELECT id, item_number, title, description, image_url,
                fair_market_value, starting_bid, min_increment, buy_now_price,
                current_high_bid, current_high_bidder_id, auction_end_time,
                is_closed
         FROM items WHERE id = ?",
        [(int)$item_id]
    );
}

/**
 * Calculate next eligible bid amount
 * @param float $current_high_bid Current highest bid
 * @param float $min_increment Minimum increment
 * @return float Next eligible bid
 */
function calculateNextBid($current_high_bid, $min_increment) {
    return $current_high_bid + $min_increment;
}

/**
 * Round bid money to two decimals.
 * @param float $amount
 * @return float
 */
function normalizeBidMoney($amount) {
    return round((float)$amount, 2);
}

/**
 * Get the active high bidder's max/proxy ceiling for an item.
 * Falls back to the visible high bid when no max bid exists.
 * @param int $item_id Item ID
 * @param int|null $high_bidder_id Current high bidder ID
 * @param float $current_high_bid Current visible high bid
 * @return float
 */
function getHighBidderCeiling($item_id, $high_bidder_id, $current_high_bid) {
    if (!$high_bidder_id) {
        return normalizeBidMoney($current_high_bid);
    }

    $ceiling = dbGetValue(
        "SELECT MAX(COALESCE(max_bid_amount, bid_amount))
         FROM bids
         WHERE item_id = ? AND user_id = ?",
        [(int)$item_id, (int)$high_bidder_id]
    );

    return normalizeBidMoney(max((float)($ceiling ?? 0), (float)$current_high_bid));
}

/**
 * Update item bid state, optionally extending the auction.
 * @param int $item_id Item ID
 * @param float $new_high_bid New visible high bid
 * @param int $new_high_bidder_id New high bidder
 * @param string|null $new_end_time New end time if anti-sniping applies
 * @return bool
 */
function updateItemBidState($item_id, $new_high_bid, $new_high_bidder_id, $new_end_time = null) {
    if ($new_end_time) {
        return (bool)dbUpdate(
            "UPDATE items SET current_high_bid = ?, current_high_bidder_id = ?,
                    auction_end_time = ? WHERE id = ?",
            [normalizeBidMoney($new_high_bid), (int)$new_high_bidder_id, $new_end_time, (int)$item_id]
        );
    }

    return (bool)dbUpdate(
        "UPDATE items SET current_high_bid = ?, current_high_bidder_id = ? WHERE id = ?",
        [normalizeBidMoney($new_high_bid), (int)$new_high_bidder_id, (int)$item_id]
    );
}

/**
 * Check if bid is valid and meets minimum requirements
 * @param float $bid_amount Proposed bid amount
 * @param float $current_high_bid Current high bid
 * @param float $min_increment Minimum increment
 * @param float $starting_bid Starting bid (minimum allowed)
 * @return array ['valid' => bool, 'message' => string]
 */
function validateBidAmount($bid_amount, $current_high_bid, $min_increment, $starting_bid) {
    // Check if bid meets starting bid minimum
    if ($bid_amount < $starting_bid) {
        return [
            'valid' => false,
            'message' => 'Bid must be at least $' . number_format($starting_bid, 2)
        ];
    }

    // If no bids yet, just check against starting bid
    if ($current_high_bid == 0) {
        return ['valid' => true, 'message' => ''];
    }

    // Check if bid meets next minimum
    $next_minimum = calculateNextBid($current_high_bid, $min_increment);
    if ($bid_amount < $next_minimum) {
        return [
            'valid' => false,
            'message' => 'Bid must be at least $' . number_format($next_minimum, 2)
        ];
    }

    return ['valid' => true, 'message' => ''];
}

/**
 * Place a bid with MySQL transaction locking to prevent race conditions
 * @param int $item_id Item ID
 * @param int $user_id User ID
 * @param float $bid_amount Bid amount
 * @param float|null $max_bid_amount Optional max bid for proxy bidding
 * @return array ['status' => 'success'|'error', 'message' => string, ...]
 */
function placeBidWithLocking($item_id, $user_id, $bid_amount, $max_bid_amount = null) {
    // Use the shared singleton connection so the FOR UPDATE lock below and the
    // queries inside placeBid() (which go through getDB()) run on the SAME
    // connection/transaction. Previously this used an undefined `global $mysqli`
    // (always null), which fatally crashed every bid.
    $mysqli = getDB();

    try {
        // Start a transaction; the SELECT ... FOR UPDATE below takes the row lock
        // that actually prevents concurrent bids from racing. (The previous code
        // passed MYSQLI_TRANS_START_READ_COMMITTED, which is not a real mysqli
        // constant — READ COMMITTED is an isolation level, not a start flag — and
        // fatally errored.)
        $mysqli->begin_transaction();

        // Lock the item row for update - this prevents other transactions from modifying it
        $lockStmt = $mysqli->prepare("SELECT current_high_bid, current_high_bidder_id, auction_end_time, is_closed FROM items WHERE id = ? FOR UPDATE");
        if (!$lockStmt) {
            throw new Exception("Prepare failed: " . $mysqli->error);
        }

        $lockStmt->bind_param('i', $item_id);
        if (!$lockStmt->execute()) {
            throw new Exception("Lock acquisition failed: " . $lockStmt->error);
        }

        $lockResult = $lockStmt->get_result();
        if ($lockResult->num_rows === 0) {
            $mysqli->rollback();
            $lockStmt->close();
            return ['status' => 'error', 'message' => 'Item not found'];
        }

        $lockResult->close();
        $lockStmt->close();

        // Now do the actual bid placement with the lock held
        $result = placeBid($item_id, $user_id, $bid_amount, $max_bid_amount);

        if ($result['status'] === 'success') {
            $mysqli->commit();
        } else {
            $mysqli->rollback();
        }

        return $result;

    } catch (Exception $e) {
        if ($mysqli->inTransaction()) {
            $mysqli->rollback();
        }
        error_log('[BID] Transaction error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Place a bid on an item
 * @param int $item_id Item ID
 * @param int $user_id User ID
 * @param float $bid_amount Bid amount
 * @param float|null $max_bid_amount Optional max bid for proxy bidding
 * @return array ['status' => 'success'|'error', 'message' => string, ...]
 */
function placeBid($item_id, $user_id, $bid_amount, $max_bid_amount = null) {
    // Get item state
    $item = getItemState($item_id);
    if (!$item) {
        return ['status' => 'error', 'message' => 'Item not found'];
    }

    // Check if auction is closed
    if ($item['is_closed']) {
        return ['status' => 'error', 'message' => 'Auction for this item is closed'];
    }

    // Check if auction has ended
    if (strtotime($item['auction_end_time']) < time()) {
        return ['status' => 'error', 'message' => 'Auction time has expired'];
    }

    // You can't outbid yourself — if you're already the highest bidder there's
    // nothing to raise. This is what allowed the same amount to be submitted
    // repeatedly on an item you were already winning.
    if ((int)($item['current_high_bidder_id'] ?? 0) === (int)$user_id) {
        return ['status' => 'error', 'message' => "You're already the highest bidder on this item."];
    }

    // Validate bid amount
    $validation = validateBidAmount(
        $bid_amount,
        (float)$item['current_high_bid'],
        (float)$item['min_increment'],
        (float)$item['starting_bid']
    );

    if (!$validation['valid']) {
        return ['status' => 'error', 'message' => $validation['message']];
    }

    $bid_amount = normalizeBidMoney($bid_amount);
    $max_bid_amount = $max_bid_amount !== null ? normalizeBidMoney($max_bid_amount) : null;
    $user_ceiling = $max_bid_amount !== null ? $max_bid_amount : $bid_amount;

    // Validate max_bid if provided
    if ($max_bid_amount !== null && $max_bid_amount < $bid_amount) {
        return ['status' => 'error', 'message' => 'Max bid must be >= current bid'];
    }

    // --- Buy It Now ---
    // If the item has a buy-now price and the bid meets or exceeds it, this is an
    // instant purchase: the bidder wins immediately at the buy-now price and the
    // auction closes. (Runs inside the same locked transaction as a normal bid.)
    $buy_now_price = isset($item['buy_now_price']) ? (float)$item['buy_now_price'] : 0.0;
    if ($buy_now_price > 0 && $bid_amount >= $buy_now_price) {
        $win_amount = normalizeBidMoney($buy_now_price);
        $prior_bidder = (int)($item['current_high_bidder_id'] ?? 0);

        $bid_id = dbInsert(
            "INSERT INTO bids (item_id, user_id, bid_amount, max_bid_amount, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [(int)$item_id, (int)$user_id, $win_amount, null]
        );
        if (!$bid_id) {
            return ['status' => 'error', 'message' => 'Failed to place bid'];
        }

        // Win + close immediately.
        dbUpdate(
            "UPDATE items SET current_high_bid = ?, current_high_bidder_id = ?, is_closed = 1 WHERE id = ?",
            [$win_amount, (int)$user_id, (int)$item_id]
        );

        dbInsert(
            "INSERT INTO audit_log (event_type, user_id, item_id, description, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            ['BUY_NOW', (int)$user_id, (int)$item_id, 'Bought now for $' . $win_amount]
        );

        return [
            'status' => 'success',
            'message' => 'Purchased with Buy It Now',
            'bid_id' => $bid_id,
            'proxy_bid_id' => null,
            'new_high_bid' => (float)$win_amount,
            'next_minimum' => (float)$win_amount,
            'auction_end_time' => $item['auction_end_time'],
            'time_remaining_ms' => 0,
            'was_anti_sniping_applied' => false,
            'was_proxy_applied' => false,
            'is_user_winning' => true,
            'is_closed' => true,
            'buy_now' => true,
            'proxy_message' => '',
            // Notify a prior high bidder (if any, and not the buyer) that they lost.
            'previous_high_bidder_id' => ($prior_bidder && $prior_bidder !== (int)$user_id) ? $prior_bidder : null
        ];
    }

    $previous_high_bidder_id = $item['current_high_bidder_id'];
    $current_high_bid = normalizeBidMoney((float)$item['current_high_bid']);
    $min_increment = normalizeBidMoney((float)$item['min_increment']);
    $is_first_bid = $current_high_bid <= 0;
    $incumbent_ceiling = getHighBidderCeiling($item_id, $previous_high_bidder_id, $current_high_bid);

    $new_high_bidder_id = (int)$user_id;
    $new_high_bid = $bid_amount;
    $proxy_bid_id = null;
    $was_proxy_applied = false;
    $is_user_winning_after_bid = true;
    $proxy_message = '';

    if ($is_first_bid) {
        $new_high_bid = $bid_amount;
    } elseif ((int)$previous_high_bidder_id === (int)$user_id) {
        // Existing winner is increasing or confirming their max. Keep visible price stable.
        $new_high_bidder_id = (int)$user_id;
        $new_high_bid = $current_high_bid;
        $proxy_message = 'Your max bid was updated.';
    } elseif ($previous_high_bidder_id) {
        if ($user_ceiling > $incumbent_ceiling) {
            // New bidder wins, paying one increment above the incumbent ceiling when needed.
            $new_high_bidder_id = (int)$user_id;
            $new_high_bid = normalizeBidMoney(max($bid_amount, min($user_ceiling, $incumbent_ceiling + $min_increment)));
            $was_proxy_applied = $new_high_bid > $bid_amount;
            $proxy_message = $was_proxy_applied
                ? 'Your max bid took the lead automatically.'
                : '';
        } else {
            // Incumbent stays ahead through their stored max bid.
            $new_high_bidder_id = (int)$previous_high_bidder_id;
            $new_high_bid = normalizeBidMoney(min($incumbent_ceiling, $user_ceiling + $min_increment));
            $is_user_winning_after_bid = false;
            $was_proxy_applied = true;
            $proxy_message = $user_ceiling === $incumbent_ceiling
                ? 'Another bidder reached that max first, so they are still winning.'
                : 'Another bidder has a higher max bid and is still winning.';
        }
    }

    // Insert the visible bid record for the bidder.
    $visible_user_bid = $is_user_winning_after_bid ? $new_high_bid : $user_ceiling;
    $bid_id = dbInsert(
        "INSERT INTO bids (item_id, user_id, bid_amount, max_bid_amount, created_at)
         VALUES (?, ?, ?, ?, NOW())",
        [(int)$item_id, (int)$user_id, normalizeBidMoney($visible_user_bid), $max_bid_amount]
    );

    if (!$bid_id) {
        return ['status' => 'error', 'message' => 'Failed to place bid'];
    }

    // If the incumbent's max bid automatically countered, record that visible counter-bid.
    if (!$is_user_winning_after_bid && $previous_high_bidder_id) {
        $proxy_bid_id = dbInsert(
            "INSERT INTO bids (item_id, user_id, bid_amount, max_bid_amount, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [
                (int)$item_id,
                (int)$previous_high_bidder_id,
                normalizeBidMoney($new_high_bid),
                normalizeBidMoney($incumbent_ceiling)
            ]
        );
    }

    // Update item with new high bid
    $time_remaining = strtotime($item['auction_end_time']) - time();
    $should_extend = $time_remaining > 0 && $time_remaining <= (ANTI_SNIPING_MINUTES * 60);
    $new_end_time = null;

    if ($should_extend) {
        // Extend auction by ANTI_SNIPING_MINUTES
        $new_end_time = date(
            'Y-m-d H:i:s',
            strtotime($item['auction_end_time']) + (ANTI_SNIPING_MINUTES * 60)
        );
    }

    updateItemBidState($item_id, $new_high_bid, $new_high_bidder_id, $new_end_time);

    // Log audit event
    dbInsert(
        "INSERT INTO audit_log (event_type, user_id, item_id, description, created_at)
         VALUES (?, ?, ?, ?, NOW())",
        [
            'BID_PLACED',
            (int)$user_id,
            (int)$item_id,
            'Bid placed: $' . $visible_user_bid . ($max_bid_amount ? ' max $' . $max_bid_amount : '')
        ]
    );

    // Get updated item state
    $updated_item = getItemState($item_id);
    $next_minimum = calculateNextBid((float)$updated_item['current_high_bid'], (float)$item['min_increment']);

    return [
        'status' => 'success',
        'message' => 'Bid placed successfully',
        'bid_id' => $bid_id,
        'proxy_bid_id' => $proxy_bid_id,
        'new_high_bid' => (float)$updated_item['current_high_bid'],
        'next_minimum' => $next_minimum,
        'auction_end_time' => $updated_item['auction_end_time'],
        'time_remaining_ms' => max(0, (strtotime($updated_item['auction_end_time']) - time()) * 1000),
        'was_anti_sniping_applied' => $should_extend,
        'was_proxy_applied' => $was_proxy_applied,
        'is_user_winning' => (int)$updated_item['current_high_bidder_id'] === (int)$user_id,
        'proxy_message' => $proxy_message,
        'previous_high_bidder_id' => ((int)$updated_item['current_high_bidder_id'] === (int)$previous_high_bidder_id)
            ? null
            : $previous_high_bidder_id
    ];
}

/**
 * Get recent bids for an item (for live feed)
 * @param int $item_id Item ID
 * @param int $limit Number of bids to return
 * @return array Array of bid records
 */
function getRecentBids($item_id, $limit = 20) {
    return dbGetAll(
        "SELECT b.id, b.bid_amount, b.created_at, u.full_name
         FROM bids b
         JOIN users u ON u.id = b.user_id
         WHERE b.item_id = ?
         ORDER BY b.created_at DESC, b.id DESC
         LIMIT ?",
        [(int)$item_id, (int)$limit]
    );
}

/**
 * Get bid history for a user on a specific item
 * @param int $user_id User ID
 * @param int $item_id Item ID
 * @return array
 */
function getUserBidsOnItem($user_id, $item_id) {
    return dbGetAll(
        "SELECT bid_amount, max_bid_amount, created_at
         FROM bids
         WHERE user_id = ? AND item_id = ?
         ORDER BY created_at DESC",
        [(int)$user_id, (int)$item_id]
    );
}

/**
 * Check if user is currently winning an item
 * @param int $user_id User ID
 * @param int $item_id Item ID
 * @return bool
 */
function isUserWinning($user_id, $item_id) {
    $item = getItemState($item_id);
    return $item && $item['current_high_bidder_id'] == $user_id;
}

/**
 * Get all items a user has bid on
 * @param int $user_id User ID
 * @return array
 */
function getUserBidHistory($user_id) {
    return dbGetAll(
        "SELECT DISTINCT i.id, i.item_number, i.title, i.current_high_bid,
                i.current_high_bidder_id, i.is_closed, i.auction_end_time
         FROM items i
         JOIN bids b ON b.item_id = i.id
         WHERE b.user_id = ?
         ORDER BY i.created_at DESC",
        [(int)$user_id]
    );
}
