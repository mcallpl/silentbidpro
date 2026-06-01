<?php
// ============================================================
// API ENDPOINT: Get Item Details
// GET /api/bidding/get-item.php?id=ITEM_ID
// ============================================================


require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/bidding.php';

header('Content-Type: application/json');

// CRITICAL: Prevent all caching - bids must sync in real-time
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

// Get item ID from query string
$item_id = (int)($_GET['id'] ?? 0);
if (!$item_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Item ID required']));
}

// Get optional authentication (for determining if user is winning)
$user = null;
$token = getSessionToken();
if ($token) {
    $user = validateSessionToken($token);
}

// Get item state
$item = getItemState($item_id);
if (!$item) {
    http_response_code(404);
    die(json_encode(['status' => 'error', 'message' => 'Item not found']));
}

// Calculate time remaining
$time_remaining = strtotime($item['auction_end_time']) - time();
$time_remaining_ms = max(0, $time_remaining * 1000);

// Calculate next minimum bid
$next_minimum = $item['current_high_bid'] > 0
    ? $item['current_high_bid'] + (float)$item['min_increment']
    : (float)$item['starting_bid'];

// Check if current user is winning
$is_user_winning = $user && $item['current_high_bidder_id'] == $user['id'];

// Build response
$response = [
    'status' => 'ok',
    'item' => [
        'id' => (int)$item['id'],
        'item_number' => (int)$item['item_number'],
        'title' => $item['title'],
        'description' => $item['description'],
        'image_url' => $item['image_url'],
        'fair_market_value' => (float)$item['fair_market_value'],
        'starting_bid' => (float)$item['starting_bid'],
        'min_increment' => (float)$item['min_increment'],
        'buy_now_price' => $item['buy_now_price'] ? (float)$item['buy_now_price'] : null,
        'current_high_bid' => (float)$item['current_high_bid'],
        'next_minimum' => $next_minimum,
        'auction_end_time' => $item['auction_end_time'],
        'time_remaining_ms' => $time_remaining_ms,
        'is_closed' => (bool)$item['is_closed'],
        'is_user_winning' => $is_user_winning,
        'high_bidder_name' => $is_user_winning ? 'You' : 'Someone else'
    ]
];

http_response_code(200);
echo json_encode($response);

?>
