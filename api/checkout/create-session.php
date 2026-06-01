<?php
// ============================================================
// API ENDPOINT: Create Stripe Checkout Session
// POST /api/checkout/create-session.php
// ============================================================


require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/stripe-utils.php';

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

// Require authentication
$user = requireAuth();

// Get input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid JSON']));
}

$item_id = (int)($input['item_id'] ?? 0);
if (!$item_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Item ID required']));
}

// Get item and verify user is winner
$item = dbGetRow(
    "SELECT id, title, current_high_bid, current_high_bidder_id FROM items WHERE id = ?",
    [(int)$item_id]
);

if (!$item) {
    http_response_code(404);
    die(json_encode(['status' => 'error', 'message' => 'Item not found']));
}

if ($item['current_high_bidder_id'] != $user['id']) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'You are not the winner of this item']));
}

// Create Stripe checkout session
$result = createCheckoutSession(
    $item_id,
    $user['id'],
    (float)$item['current_high_bid'],
    $item['title'],
    '' // email can be added later if available
);

if (!$result['success']) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => $result['error']]));
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'session_id' => $result['session_id'],
    'public_key' => $result['public_key']
]);

?>
