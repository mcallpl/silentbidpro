<?php
// ============================================================
// AUCTION ENGINE
// Auto-closing, winner processing, and background tasks
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/payment-requests.php';
require_once __DIR__ . '/event-notifier.php';

/**
 * Close expired auctions and process winners
 * Called periodically (cron or on-demand)
 * @return array ['closed_count' => int, 'errors' => array]
 */
function closeExpiredAuctions() {
    $errors = [];
    $closed_count = 0;

    // Serialize the whole closer with a MySQL advisory lock so overlapping cron
    // ticks (a slow run that exceeds 60s) or a concurrent admin "Close Auctions"
    // click can't both process the same winners → duplicate payment requests and
    // duplicate "You won!" SMS/push. Non-blocking: if another run holds it, bail.
    $got_lock = (int)dbGetValue("SELECT GET_LOCK('sbp_auction_close', 0)");
    if ($got_lock !== 1) {
        return ['closed_count' => 0, 'errors' => ['Another auction-close run is in progress; skipped.']];
    }

    try {

    // Get all expired items that haven't been closed yet. Use the EFFECTIVE close
    // time (close_time_override when set) so items with a custom close are handled.
    $expired_items = dbGetAll(
        "SELECT id, item_number, title, current_high_bid, current_high_bidder_id
         FROM items
         WHERE COALESCE(close_time_override, auction_end_time) <= NOW() AND is_closed = 0"
    );

    foreach ($expired_items as $item) {
        try {
            // Re-read fresh state under the lock. A bid may have landed at the close
            // second and triggered an anti-sniping extension (or a Buy It Now closed
            // it) between the SELECT above and now — in which case skip it.
            $fresh = dbGetRow(
                "SELECT id, title, current_high_bid, current_high_bidder_id, is_closed
                 FROM items
                 WHERE id = ? AND is_closed = 0
                   AND COALESCE(close_time_override, auction_end_time) <= NOW()",
                [(int)$item['id']]
            );
            if (!$fresh) {
                continue; // extended, already closed, or claimed elsewhere
            }
            $item = $fresh;
            // Process the winner BEFORE marking the item closed. Previously the
            // item was closed first and processWinner()'s result was discarded,
            // so a failed transaction/notification silently left the winner with
            // no payment request and no way to retry (the closed item is excluded
            // from the next run). Now: only close once winner processing succeeds,
            // so a transient failure is retried on the next cron tick and shows up
            // in the returned errors meanwhile.
            if ($item['current_high_bidder_id']) {
                $winner = dbGetRow(
                    "SELECT id, phone_number, full_name FROM users WHERE id = ?",
                    [(int)$item['current_high_bidder_id']]
                );

                if (!$winner) {
                    // Unrecoverable (winner user is gone). Log, close so we don't
                    // loop on it forever, and move on.
                    $errors[] = "Item {$item['id']}: high bidder {$item['current_high_bidder_id']} not found; closing without winner processing";
                    error_log($errors[count($errors) - 1]);
                } else {
                    $ok = processWinner(
                        (int)$item['id'],
                        (int)$winner['id'],
                        (float)$item['current_high_bid'],
                        $item['title'],
                        $winner['phone_number']
                    );
                    if (!$ok) {
                        // Retryable — leave the item OPEN so the next run tries again.
                        $errors[] = "Item {$item['id']}: winner processing failed; left open for retry";
                        error_log($errors[count($errors) - 1]);
                        continue;
                    }
                }
            }

            // Mark as closed — compare-and-swap: only if still open and still past
            // its effective close. If it was extended/closed in the race window
            // this affects 0 rows and we don't double-count it.
            $affected = dbUpdate(
                "UPDATE items SET is_closed = 1
                 WHERE id = ? AND is_closed = 0
                   AND COALESCE(close_time_override, auction_end_time) <= NOW()",
                [(int)$item['id']]
            );
            if ($affected < 1) {
                continue;
            }
            $closed_count++;

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

    } finally {
        dbGetValue("SELECT RELEASE_LOCK('sbp_auction_close')");
    }
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
    $payment_request = ensurePendingPaymentRequest($item_id, $user_id, $amount);

    if (!$payment_request['success']) {
        error_log("Failed to create transaction for winner {$user_id} on item {$item_id}");
        return false;
    }

    if ($payment_request['already_paid'] || !$payment_request['created']) {
        return true;
    }

    // Generate checkout URL
    $checkout_url = APP_DOMAIN . '/checkout.php?item_id=' . urlencode($item_id)
        . '&user_id=' . urlencode($user_id);

    // Send push and SMS notifications (respects event SMS settings)
    notifyWinner($item_id, $user_id, $item_title, $amount, $checkout_url);

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
 * Build an event-scope SQL fragment for metrics queries.
 * @param array|null $eventIds  null = all, [] = none, [ids] = restrict
 * @param string $col           qualified event_id column
 * @return array [sqlFragment, params]
 */
function metricsEventFilter($eventIds, $col) {
    if ($eventIds === null) {
        return ['', []];
    }
    if (empty($eventIds)) {
        return [" AND 1=0", []]; // no accessible events → match nothing
    }
    $ph = implode(',', array_fill(0, count($eventIds), '?'));
    return [" AND {$col} IN ({$ph})", $eventIds];
}

/**
 * Get live auction metrics
 * @param array|null $eventIds Optional event scope (null=all, []=none, [ids])
 * @return array Metrics data
 */
function getLiveMetrics($eventIds = null) {
    list($fi, $pi) = metricsEventFilter($eventIds, 'event_id');   // items table
    list($fbi, $pbi) = metricsEventFilter($eventIds, 'i.event_id'); // bids joined to items

    $active_items = dbGetValue(
        "SELECT COUNT(*) FROM items WHERE is_closed = 0 AND auction_end_time > NOW(){$fi}",
        $pi
    );

    $active_bidders = dbGetValue(
        "SELECT COUNT(DISTINCT b.user_id) FROM bids b JOIN items i ON i.id = b.item_id
         WHERE b.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR){$fbi}",
        $pbi
    );

    $total_bids = dbGetValue(
        "SELECT COUNT(*) FROM bids b JOIN items i ON i.id = b.item_id
         WHERE b.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR){$fbi}",
        $pbi
    );

    // Total raised from CLOSED items only
    $total_raised = dbGetValue(
        "SELECT SUM(current_high_bid) FROM items
         WHERE is_closed = 1 AND current_high_bid > 0{$fi}",
        $pi
    );

    $recent_bids = dbGetAll(
        "SELECT b.bid_amount, b.created_at, i.title, u.full_name
         FROM bids b
         JOIN items i ON i.id = b.item_id
         JOIN users u ON u.id = b.user_id
         WHERE b.created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE){$fbi}
         ORDER BY b.created_at DESC
         LIMIT 10",
        $pbi
    );

    // High traffic items - ranked by bid count
    $high_traffic_items = dbGetAll(
        "SELECT i.id, i.title, i.image_url, COUNT(b.id) as bid_count, i.current_high_bid, i.is_closed
         FROM items i
         LEFT JOIN bids b ON b.item_id = i.id
         WHERE 1=1{$fi}
         GROUP BY i.id, i.title, i.image_url, i.current_high_bid, i.is_closed
         ORDER BY bid_count DESC
         LIMIT 5",
        $pi
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
 * @param array|null $eventIds Optional event scope (null=all, []=none, [ids])
 * @return array Summary data
 */
function getAuctionSummary($eventIds = null) {
    list($fi, $pi) = metricsEventFilter($eventIds, 'event_id');    // items
    list($fbi, $pbi) = metricsEventFilter($eventIds, 'i.event_id'); // bids/tx joined to items

    $total_items = dbGetValue("SELECT COUNT(*) FROM items WHERE 1=1{$fi}", $pi);
    $closed_items = dbGetValue("SELECT COUNT(*) FROM items WHERE is_closed = 1{$fi}", $pi);
    $total_bidders = dbGetValue(
        "SELECT COUNT(DISTINCT b.user_id) FROM bids b JOIN items i ON i.id = b.item_id WHERE 1=1{$fbi}", $pbi);
    $total_bids = dbGetValue(
        "SELECT COUNT(*) FROM bids b JOIN items i ON i.id = b.item_id WHERE 1=1{$fbi}", $pbi);
    // Total raised from CLOSED items only
    $total_raised = dbGetValue(
        "SELECT SUM(current_high_bid) FROM items WHERE is_closed = 1 AND current_high_bid > 0{$fi}", $pi);
    $pending_payments = dbGetValue(
        "SELECT COUNT(*) FROM transactions t JOIN items i ON i.id = t.item_id WHERE t.status = 'pending'{$fbi}", $pbi);
    $completed_payments = dbGetValue(
        "SELECT SUM(t.amount) FROM transactions t JOIN items i ON i.id = t.item_id WHERE t.status = 'paid'{$fbi}", $pbi);

    return [
        'total_items' => (int)$total_items,
        'closed_items' => (int)$closed_items,
        'active_items' => max(0, (int)$total_items - (int)$closed_items),
        'total_bidders' => (int)$total_bidders,
        'total_bids' => (int)$total_bids,
        'total_raised' => (float)($total_raised ?? 0),
        'pending_payments' => (int)$pending_payments,
        'completed_payments' => (float)($completed_payments ?? 0),
        'completion_rate' => $total_items > 0 ? round(((int)$closed_items / (int)$total_items) * 100, 1) : 0
    ];
}
