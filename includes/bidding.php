<?php
// ============================================================
// BIDDING ENGINE
// Core bid logic, proxy bidding, anti-sniping mechanics
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';
require_once __DIR__ . '/events.php'; // getEffectiveItemCloseTime()

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
                close_time_override, is_closed
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

    // Check if bid meets next minimum. Compare in integer cents so binary
    // float error (e.g. 10.30 + 0.05 = 10.350000000000001) can't reject a bid
    // of exactly the advertised minimum.
    $next_minimum = round(calculateNextBid($current_high_bid, $min_increment), 2);
    if (round($bid_amount * 100) < round($next_minimum * 100)) {
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
 * @param bool $is_buy_now Explicit Buy It Now intent from the client
 * @return array ['status' => 'success'|'error', 'message' => string, ...]
 */
function placeBidWithLocking($item_id, $user_id, $bid_amount, $max_bid_amount = null, $is_buy_now = false) {
    // Use the shared singleton connection so the FOR UPDATE lock below and the
    // queries inside placeBid() (which go through getDB()) run on the SAME
    // connection/transaction. Previously this used an undefined `global $mysqli`
    // (always null), which fatally crashed every bid.
    $mysqli = getDB();

    $in_transaction = false;
    try {
        // Start a transaction; the SELECT ... FOR UPDATE below takes the row lock
        // that actually prevents concurrent bids from racing. (The previous code
        // passed MYSQLI_TRANS_START_READ_COMMITTED, which is not a real mysqli
        // constant — READ COMMITTED is an isolation level, not a start flag — and
        // fatally errored.)
        $mysqli->begin_transaction();
        $in_transaction = true;

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
        $result = placeBid($item_id, $user_id, $bid_amount, $max_bid_amount, $is_buy_now);

        if ($result['status'] === 'success') {
            $mysqli->commit();
        } else {
            $mysqli->rollback();
        }
        $in_transaction = false;

        return $result;

    } catch (\Throwable $e) {
        // mysqli has no inTransaction() method (that's PDO); calling it here would
        // throw a second fatal inside the catch and mask the real error. Track the
        // state ourselves and roll back unconditionally when a tx is open.
        if ($in_transaction) {
            try { $mysqli->rollback(); } catch (\Throwable $ignored) {}
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
 * @param bool $is_buy_now Explicit Buy It Now intent from the client
 * @return array ['status' => 'success'|'error', 'message' => string, ...]
 */
function placeBid($item_id, $user_id, $bid_amount, $max_bid_amount = null, $is_buy_now = false) {
    // Get item state
    $item = getItemState($item_id);
    if (!$item) {
        return ['status' => 'error', 'message' => 'Item not found'];
    }

    // Check if auction is closed
    if ($item['is_closed']) {
        return ['status' => 'error', 'message' => 'Auction for this item is closed'];
    }

    // Check if auction has ended. Use the EFFECTIVE close time (close_time_override
    // when set) so the bid gate matches what the listings and countdown show.
    $effective_close = getEffectiveItemCloseTime($item);
    if ($effective_close && strtotime($effective_close) < time()) {
        return ['status' => 'error', 'message' => 'Auction time has expired'];
    }

    // Normalize money up front so every branch below compares clean 2-dp values.
    $bid_amount = normalizeBidMoney($bid_amount);
    $max_bid_amount = $max_bid_amount !== null ? normalizeBidMoney($max_bid_amount) : null;
    if ($max_bid_amount !== null && $max_bid_amount < $bid_amount) {
        return ['status' => 'error', 'message' => 'Max bid must be >= current bid'];
    }
    $user_ceiling = $max_bid_amount !== null ? $max_bid_amount : $bid_amount;
    $is_incumbent = (int)($item['current_high_bidder_id'] ?? 0) === (int)$user_id;

    // --- Buy It Now ---
    // Only entered on EXPLICIT client intent ($is_buy_now). Previously any bid
    // whose amount happened to meet the buy-now price was silently converted
    // into an instant purchase that closed the auction — several testers "won"
    // items by accident that way. Now a regular bid that reaches buy-now gets a
    // distinct error telling the client to show the buy-now confirmation.
    // Evaluated BEFORE increment validation and the self-bid guard, so buying
    // now always works — even from the current leader. The winner's payment
    // request is created by the caller AFTER commit via the 'buy_now' flag, so
    // we never hold the row lock during Stripe/Twilio/push calls.
    $buy_now_price = isset($item['buy_now_price']) ? (float)$item['buy_now_price'] : 0.0;
    if (!$is_buy_now && $buy_now_price > 0 && $bid_amount >= $buy_now_price) {
        return [
            'status' => 'error',
            'code' => 'meets_buy_now',
            'message' => 'That amount meets the Buy It Now price of $' . number_format($buy_now_price, 2)
                . '. Buying now wins the item instantly and ends bidding — please confirm.',
            'buy_now_price' => $buy_now_price
        ];
    }
    if ($is_buy_now && !($buy_now_price > 0)) {
        return ['status' => 'error', 'message' => 'Buy It Now is not available for this item'];
    }
    if ($is_buy_now) {
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

        // Win + close immediately (fail the bid if the state update didn't land,
        // so the transaction rolls back rather than reporting a phantom purchase).
        if (!dbUpdate(
            "UPDATE items SET current_high_bid = ?, current_high_bidder_id = ?, is_closed = 1 WHERE id = ?",
            [$win_amount, (int)$user_id, (int)$item_id]
        )) {
            return ['status' => 'error', 'message' => 'Failed to finalize purchase'];
        }

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

    // Self-bid guard: block only a true no-op. The current leader may still RAISE
    // their proxy max (handled by the proxy branch below); reject only when they
    // aren't actually increasing it. (Buy It Now was already handled above.)
    if ($is_incumbent) {
        $current_ceiling = getHighBidderCeiling($item_id, (int)$user_id, (float)$item['current_high_bid']);
        if ($max_bid_amount === null || round($user_ceiling * 100) <= round($current_ceiling * 100)) {
            return ['status' => 'error', 'message' => "You're already the highest bidder on this item."];
        }
    }

    // Validate bid amount (skip for the incumbent raising their max — they already
    // lead, so the "beat the current price" rule doesn't apply to a max increase).
    // An item only truly "has bids" when there is a live high BIDDER. Seeded or
    // reset data can leave current_high_bid > 0 with no bidder — the displayed
    // minimum (starting bid) and the validation minimum must agree on that case,
    // or the advertised Quick Bid amount gets rejected.
    $validation_high_bid = !empty($item['current_high_bidder_id'])
        ? (float)$item['current_high_bid']
        : 0.0;

    if (!$is_incumbent) {
        $validation = validateBidAmount(
            $bid_amount,
            $validation_high_bid,
            (float)$item['min_increment'],
            (float)$item['starting_bid']
        );
        if (!$validation['valid']) {
            // Include the CURRENT minimum so a client whose quick-bid amount
            // went stale (someone bid in the polling gap) can refresh its UI
            // and re-prompt instead of dead-ending on an error.
            $fresh_minimum = $validation_high_bid > 0
                ? round(calculateNextBid($validation_high_bid, (float)$item['min_increment']), 2)
                : (float)$item['starting_bid'];
            return [
                'status' => 'error',
                'code' => 'bid_too_low',
                'message' => $validation['message'],
                'next_minimum' => $fresh_minimum,
                'current_high_bid' => (float)$item['current_high_bid']
            ];
        }
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

    // Never let proxy escalation push the visible price to or past the buy-now
    // price — otherwise the standing price could exceed buy-now and a later
    // buy-now purchase would undercut the current leader.
    if ($buy_now_price > 0 && $new_high_bid > $buy_now_price) {
        $new_high_bid = normalizeBidMoney($buy_now_price);
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

    // Update item with new high bid. Anti-sniping is measured against the
    // EFFECTIVE close time so it matches the bid gate and the displayed countdown.
    $close_basis = $effective_close ?: $item['auction_end_time'];
    $time_remaining = strtotime($close_basis) - time();
    $should_extend = $time_remaining > 0 && $time_remaining <= (ANTI_SNIPING_MINUTES * 60);
    $new_end_time = null;

    if ($should_extend) {
        // Extend auction by ANTI_SNIPING_MINUTES
        $new_end_time = date(
            'Y-m-d H:i:s',
            strtotime($close_basis) + (ANTI_SNIPING_MINUTES * 60)
        );
    }

    // A failed state update must fail the whole bid so the caller rolls back,
    // rather than committing the bid row while items.current_high_bid stays stale.
    if (!updateItemBidState($item_id, $new_high_bid, $new_high_bidder_id, $new_end_time)) {
        return ['status' => 'error', 'message' => 'Failed to update auction state'];
    }

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
