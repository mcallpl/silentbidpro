<?php
// ============================================================
// API ENDPOINT: Get User Details with Bid History
// GET /api/admin/get-user-details.php?user_id=X
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

requireAdminAuth();

$user_id = (int)($_GET['user_id'] ?? 0);

if (!$user_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'user_id is required']));
}

// Get user info
$user = dbGetRow(
    "SELECT id, full_name, phone_number, stripe_customer_id, created_at FROM users WHERE id = ?",
    [$user_id]
);

if (!$user) {
    http_response_code(404);
    die(json_encode(['status' => 'error', 'message' => 'User not found']));
}

// Get bid history
$bid_history = dbGetAll(
    "SELECT
        b.id,
        b.item_id,
        b.bid_amount,
        b.max_bid_amount,
        b.created_at,
        i.title as item_title,
        i.current_high_bidder_id,
        i.current_high_bid,
        i.is_closed
     FROM bids b
     JOIN items i ON b.item_id = i.id
     WHERE b.user_id = ?
     ORDER BY b.created_at DESC
     LIMIT 50",
    [$user_id]
);

// Determine status for each bid
foreach ($bid_history as &$bid) {
    $bid['bid_amount'] = (float)$bid['bid_amount'];
    $bid['max_bid_amount'] = (float)($bid['max_bid_amount'] ?? 0);
    $bid['current_high_bid'] = (float)$bid['current_high_bid'];

    $is_user_high_bidder = ((int)$bid['current_high_bidder_id'] === $user_id);
    $is_this_bid_highest = ($bid['bid_amount'] === $bid['current_high_bid']);
    $is_auction_closed = ((int)$bid['is_closed'] === 1);

    if ($is_this_bid_highest && $is_user_high_bidder) {
        $bid['status'] = $is_auction_closed ? 'WON' : 'CURRENT HIGH BID';
    } else {
        $bid['status'] = 'OUTBID';
    }
}

// Get wins
$wins = dbGetAll(
    "SELECT
        i.id,
        i.title,
        i.current_high_bid as winning_amount,
        i.is_closed,
        t.status as transaction_status,
        t.created_at as transaction_created_at
     FROM items i
     LEFT JOIN transactions t ON i.id = t.item_id AND t.user_id = ?
     WHERE i.current_high_bidder_id = ? AND i.is_closed = 1
     ORDER BY i.auction_end_time DESC",
    [$user_id, $user_id]
);

// Format wins
foreach ($wins as &$win) {
    $win['winning_amount'] = (float)$win['winning_amount'];
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'user' => [
        'id' => (int)$user['id'],
        'full_name' => $user['full_name'],
        'phone_display' => substr($user['phone_number'], 0, 6) . '...' . substr($user['phone_number'], -4),
        'stripe_customer_id' => $user['stripe_customer_id'],
        'created_at' => $user['created_at']
    ],
    'bid_history' => $bid_history,
    'wins' => $wins
]);

?>
