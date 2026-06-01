<?php
// ============================================================
// TEST ENDPOINT: Test bid placement without auth
// POST /api/bidding/test-bid.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/bidding.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo json_encode([
    'test' => 'bid_placement',
    'timestamp' => date('Y-m-d H:i:s'),
    'debug_info' => [
        'method' => $_SERVER['REQUEST_METHOD'],
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'none',
        'auth_header' => $_SERVER['HTTP_AUTHORIZATION'] ?? 'none',
    ]
], JSON_PRETTY_PRINT);

// Get test user
$user = dbGetRow("SELECT id FROM users LIMIT 1");
if (!$user) {
    http_response_code(400);
    echo json_encode(['error' => 'No users in database']);
    exit;
}

// Get test item
$item = dbGetRow("SELECT id, item_number, starting_bid FROM items WHERE item_number = 104");
if (!$item) {
    http_response_code(400);
    echo json_encode(['error' => 'Item 104 not found']);
    exit;
}

echo "\nTesting bid placement:\n";
echo json_encode([
    'user_id' => $user['id'],
    'item_id' => $item['id'],
    'bid_amount' => 460.00
], JSON_PRETTY_PRINT);

$result = placeBid($item['id'], $user['id'], 460.00);
echo "\nResult:\n";
echo json_encode($result, JSON_PRETTY_PRINT);

// Verify in database
$bids = dbGetAll("SELECT COUNT(*) as cnt FROM bids WHERE item_id = ? AND user_id = ?", [(int)$item['id'], (int)$user['id']]);
echo "\nBids after placement:\n";
echo json_encode($bids, JSON_PRETTY_PRINT);
?>
