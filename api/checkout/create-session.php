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

// COMBINED MODE: one Stripe session covering every unpaid won item in an
// event (the "pay for everything at once" path).
if (!empty($input['all'])) {
    $event_id = (int)($input['event_id'] ?? 0);
    if (!$event_id) {
        // Infer: the (single) event where this user has unpaid won items.
        $event_id = (int)dbGetValue(
            "SELECT i.event_id
             FROM transactions t JOIN items i ON i.id = t.item_id
             WHERE t.user_id = ? AND t.status = 'pending'
               AND i.is_closed = 1 AND i.current_high_bidder_id = t.user_id
             ORDER BY t.id DESC LIMIT 1",
            [(int)$user['id']]
        );
    }
    if (!$event_id) {
        http_response_code(404);
        die(json_encode(['status' => 'error', 'message' => 'Nothing awaiting payment']));
    }

    $result = createCombinedCheckoutSession((int)$user['id'], $event_id);
    if (empty($result['success'])) {
        http_response_code(500);
        die(json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Checkout failed']));
    }
    http_response_code(200);
    die(json_encode([
        'status' => 'ok',
        'session_id' => $result['session_id'],
        'public_key' => $result['public_key'],
        'item_count' => $result['item_count'],
        'total' => $result['total']
    ]));
}

$item_id = (int)($input['item_id'] ?? 0);
if (!$item_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Item ID required']));
}

// Get item and verify user is winner
$item = dbGetRow(
    "SELECT id, title, current_high_bid, current_high_bidder_id, is_closed FROM items WHERE id = ?",
    [(int)$item_id]
);

if (!$item) {
    http_response_code(404);
    die(json_encode(['status' => 'error', 'message' => 'Item not found']));
}

// The auction must be CLOSED before payment. Without this, the current leading
// bidder could pay mid-auction and then be outbid — collecting money for an item
// they don't actually win. (Server-side enforcement; the UI gate is not enough.)
if (empty($item['is_closed'])) {
    http_response_code(409);
    die(json_encode(['status' => 'error', 'message' => 'This auction has not closed yet. Payment opens once bidding ends.']));
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
