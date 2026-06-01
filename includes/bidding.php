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

    // Validate max_bid if provided
    if ($max_bid_amount !== null && $max_bid_amount < $bid_amount) {
        return ['status' => 'error', 'message' => 'Max bid must be >= current bid'];
    }

    $previous_high_bidder_id = $item['current_high_bidder_id'];

    // Insert bid record
    $bid_id = dbInsert(
        "INSERT INTO bids (item_id, user_id, bid_amount, max_bid_amount, created_at)
         VALUES (?, ?, ?, ?, NOW())",
        [(int)$item_id, (int)$user_id, (float)$bid_amount, $max_bid_amount]
    );

    if (!$bid_id) {
        return ['status' => 'error', 'message' => 'Failed to place bid'];
    }

    // Update item with new high bid
    $time_remaining = strtotime($item['auction_end_time']) - time();
    $should_extend = $time_remaining > 0 && $time_remaining <= (ANTI_SNIPING_MINUTES * 60);

    if ($should_extend) {
        // Extend auction by ANTI_SNIPING_MINUTES
        $new_end_time = date(
            'Y-m-d H:i:s',
            strtotime($item['auction_end_time']) + (ANTI_SNIPING_MINUTES * 60)
        );
        dbUpdate(
            "UPDATE items SET current_high_bid = ?, current_high_bidder_id = ?,
                    auction_end_time = ? WHERE id = ?",
            [(float)$bid_amount, (int)$user_id, $new_end_time, (int)$item_id]
        );
    } else {
        dbUpdate(
            "UPDATE items SET current_high_bid = ?, current_high_bidder_id = ? WHERE id = ?",
            [(float)$bid_amount, (int)$user_id, (int)$item_id]
        );
    }

    // Log audit event
    dbInsert(
        "INSERT INTO audit_log (event_type, user_id, item_id, description, created_at)
         VALUES (?, ?, ?, ?, NOW())",
        ['BID_PLACED', (int)$user_id, (int)$item_id, 'Bid placed: $' . $bid_amount]
    );

    // Get updated item state
    $updated_item = getItemState($item_id);
    $next_minimum = calculateNextBid((float)$updated_item['current_high_bid'], (float)$item['min_increment']);

    return [
        'status' => 'success',
        'message' => 'Bid placed successfully',
        'bid_id' => $bid_id,
        'new_high_bid' => (float)$updated_item['current_high_bid'],
        'next_minimum' => $next_minimum,
        'auction_end_time' => $updated_item['auction_end_time'],
        'time_remaining_ms' => max(0, (strtotime($updated_item['auction_end_time']) - time()) * 1000),
        'was_anti_sniping_applied' => $should_extend,
        'previous_high_bidder_id' => $previous_high_bidder_id
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
         ORDER BY b.created_at DESC
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

