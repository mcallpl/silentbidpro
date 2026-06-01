<?php
// ============================================================
// API ENDPOINT: Place Bid
// POST /api/bidding/place-bid.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/bidding.php';
require_once __DIR__ . '/../../includes/notifications.php';

header('Content-Type: application/json');

// CRITICAL: Prevent all caching - bids must sync in real-time
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

// Require authentication
try {
    $user = requireAuth();
} catch (Exception $e) {
    error_log('[BID ERROR] Auth failed: ' . $e->getMessage());
    throw $e;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid JSON']));
}

$item_id = $input['item_id'] ?? 0;
$bid_amount = (float)($input['bid_amount'] ?? 0);
$max_bid_amount = !empty($input['max_bid_amount']) ? (float)$input['max_bid_amount'] : null;

if (!$item_id || $bid_amount <= 0) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid item or bid amount']));
}

// Log attempt
error_log('[BID] User ' . $user['id'] . ' attempting bid of $' . $bid_amount . ' on item ' . $item_id);

// Place bid
$result = placeBid($item_id, $user['id'], $bid_amount, $max_bid_amount);

// Log result
error_log('[BID] Result: ' . json_encode($result));

if ($result['status'] !== 'success') {
    error_log('[BID] ❌ FAILED - ' . $result['message']);
    http_response_code(400);
    die(json_encode($result));
}

error_log('[BID] ✓ SUCCESS - Bid ' . $result['bid_id'] . ' placed for $' . $bid_amount);

// Send outbid alert to previous bidder if applicable
if ($result['previous_high_bidder_id']) {
    $previous_bidder = dbGetRow(
        "SELECT phone_number, full_name FROM users WHERE id = ?",
        [(int)$result['previous_high_bidder_id']]
    );

    if ($previous_bidder) {
        $item = getItemState($item_id);
        sendOutbidAlert($previous_bidder['phone_number'], $item['title'], $item_id);
    }
}

http_response_code(200);
echo json_encode($result);

?>
