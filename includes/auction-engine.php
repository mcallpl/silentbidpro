<?php
// ============================================================
// AUCTION ENGINE
// Auto-closing, winner processing, and background tasks
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';
require_once __DIR__ . '/notifications.php';

/**
 * Close expired auctions and process winners
 * Called periodically (cron or on-demand)
 * @return array ['closed_count' => int, 'errors' => array]
 */
function closeExpiredAuctions() {
    $errors = [];
    $closed_count = 0;

    // Get all expired items that haven't been closed yet
    $expired_items = dbGetAll(
        "SELECT id, item_number, title, current_high_bid, current_high_bidder_id
         FROM items
         WHERE auction_end_time <= NOW() AND is_closed = 0"
    );

    foreach ($expired_items as $item) {
        try {
            // Mark as closed
            dbUpdate(
                "UPDATE items SET is_closed = 1 WHERE id = ?",
                [(int)$item['id']]
            );

            $closed_count++;

            // Process winner if exists
            if ($item['current_high_bidder_id']) {
                $winner = dbGetRow(
                    "SELECT id, phone_number, full_name FROM users WHERE id = ?",
                    [(int)$item['current_high_bidder_id']]
                );

                if ($winner) {
                    processWinner(
                        (int)$item['id'],
                        (int)$winner['id'],
                        (float)$item['current_high_bid'],
                        $item['title'],
                        $winner['phone_number']
                    );
                }
            }

            // Log audit event
            dbInsert(
                "INSERT INTO audit_log (event_type, item_id, description, created_at)
                 VALUES (?, ?, ?, NOW())",
                ['AUCTION_CLOSED', (int)$item['id'], 'Auction closed automatically']
            );
        } catch (Exception $e) {
            $errors[] = "Error closing item {$item['id']}: " . $e->getMessage();
            error_log($errors[count($errors) - 1]);
        }
    }

    return [
        'closed_count' => $closed_count,
        'errors' => $errors
    ];
}

/**
 * Process winner: create transaction and send notification
 * @param int $item_id Item ID
 * @param int $user_id Winner user ID
 * @param float $amount Winning bid amount
 * @param string $item_title Item title
 * @param string $phone_number Winner phone number
 * @return bool Success/failure
 */
function processWinner($item_id, $user_id, $amount, $item_title, $phone_number) {
    // Create pending transaction
    $transaction_id = dbInsert(
        "INSERT INTO transactions (user_id, item_id, amount, status, created_at)
         VALUES (?, ?, ?, ?, NOW())",
        [(int)$user_id, (int)$item_id, (float)$amount, 'pending']
    );

    if (!$transaction_id) {
        error_log("Failed to create transaction for winner {$user_id} on item {$item_id}");
        return false;
    }

    // Generate checkout URL
    $checkout_url = APP_DOMAIN . '/checkout.php?item_id=' . urlencode($item_id)
        . '&user_id=' . urlencode($user_id);

    // Send winner notification
    $sms_sent = sendWinnerNotification($phone_number, $item_title, $amount, $checkout_url);

    if (!$sms_sent) {
        error_log("Failed to send winner SMS to {$phone_number}");
    }

    return true;
}

/**
 * Clean up old verification codes and sessions
 * @return array ['deleted_codes' => int, 'deleted_sessions' => int]
 */
function cleanupExpiredRecords() {
    // Delete expired verification codes (keep for 1 day after expiration for audit)
    $deleted_codes = dbDelete(
        "DELETE FROM verification_codes
         WHERE is_used = 1 AND expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)"
    );

    // Delete expired sessions (keep for 1 day after expiration)
    $deleted_sessions = dbDelete(
        "DELETE FROM sessions
         WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)"
    );

    return [
        'deleted_codes' => $deleted_codes ?: 0,
        'deleted_sessions' => $deleted_sessions ?: 0
    ];
}

/**
 * Get live auction metrics
 * @return array Metrics data
 */
function getLiveMetrics() {
    $active_items = dbGetValue(
        "SELECT COUNT(*) FROM items WHERE is_closed = 0 AND auction_end_time > NOW()"
    );

    $active_bidders = dbGetValue(
        "SELECT COUNT(DISTINCT user_id) FROM bids
         WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );

    $total_bids = dbGetValue(
        "SELECT COUNT(*) FROM bids
         WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );

    $total_raised = dbGetValue(
        "SELECT SUM(current_high_bid) FROM items
         WHERE current_high_bid > 0"
    );

    $recent_bids = dbGetAll(
        "SELECT b.bid_amount, b.created_at, i.title, u.full_name
         FROM bids b
         JOIN items i ON i.id = b.item_id
         JOIN users u ON u.id = b.user_id
         WHERE b.created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
         ORDER BY b.created_at DESC
         LIMIT 10"
    );

    $high_traffic_items = dbGetAll(
        "SELECT i.id, i.title, i.image_url, COUNT(b.id) as bid_count, i.current_high_bid
         FROM items i
         LEFT JOIN bids b ON b.item_id = i.id
         WHERE i.is_closed = 0
         GROUP BY i.id
         ORDER BY bid_count DESC
         LIMIT 5"
    );

    return [
        'active_items' => (int)$active_items,
        'active_bidders' => (int)$active_bidders,
        'total_bids' => (int)$total_bids,
        'total_raised' => (float)($total_raised ?? 0),
        'recent_bids' => $recent_bids,
        'high_traffic_items' => $high_traffic_items
    ];
}

/**
 * Get auction summary (for admin reporting)
 * @return array Summary data
 */
function getAuctionSummary() {
    $total_items = dbGetValue("SELECT COUNT(*) FROM items");
    $closed_items = dbGetValue("SELECT COUNT(*) FROM items WHERE is_closed = 1");
    $total_bidders = dbGetValue("SELECT COUNT(DISTINCT user_id) FROM bids");
    $total_bids = dbGetValue("SELECT COUNT(*) FROM bids");
    $total_raised = dbGetValue("SELECT SUM(current_high_bid) FROM items WHERE current_high_bid > 0");
    $pending_payments = dbGetValue("SELECT COUNT(*) FROM transactions WHERE status = 'pending'");
    $completed_payments = dbGetValue("SELECT SUM(amount) FROM transactions WHERE status = 'paid'");

    return [
        'total_items' => (int)$total_items,
        'closed_items' => (int)$closed_items,
        'active_items' => max(0, (int)$total_items - (int)$closed_items),
        'total_bidders' => (int)$total_bidders,
        'total_bids' => (int)$total_bids,
        'total_raised' => (float)($total_raised ?? 0),
        'pending_payments' => (int)$pending_payments,
        'completed_payments' => (float)($completed_payments ?? 0),
        'completion_rate' => $closed_items > 0 ? round(($closed_items / $total_items) * 100, 1) : 0
    ];
}

