<?php
// ============================================================
// API ENDPOINT: Get Single Item Details
// GET /api/admin/get-item.php?id=1
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

$item_id = (int)($_GET['id'] ?? 0);

if (!$item_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Item ID required']));
}

// Get item details
$item = dbGetRow(
    "SELECT * FROM items WHERE id = ?",
    [$item_id]
);

if (!$item) {
    http_response_code(404);
    die(json_encode(['status' => 'error', 'message' => 'Item not found']));
}

// Get bid count
$bid_count = dbCount('bids', 'item_id = ?', [$item_id]);
$item['bid_count'] = $bid_count;

// Calculate time remaining
$end_time = strtotime($item['auction_end_time']);
$now = time();
$item['time_remaining_seconds'] = max(0, $end_time - $now);

// Format currency fields
$item['current_high_bid'] = (float)$item['current_high_bid'];
$item['starting_bid'] = (float)$item['starting_bid'];
$item['min_increment'] = (float)$item['min_increment'];
if ($item['fair_market_value']) {
    $item['fair_market_value'] = (float)$item['fair_market_value'];
}
if ($item['buy_now_price']) {
    $item['buy_now_price'] = (float)$item['buy_now_price'];
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'item' => $item
]);

?>
