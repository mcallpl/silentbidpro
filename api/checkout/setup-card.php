<?php
// ============================================================
// API ENDPOINT: Start card setup (save a card before first bid)
// POST /api/checkout/setup-card.php  { return_path? }
// Returns a Stripe Checkout (setup mode) URL. No charge is made —
// the saved card is only charged if the bidder wins.
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/events.php';
require_once __DIR__ . '/../../includes/card-on-file.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

$user = requireAuth();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$return_path = (string)($input['return_path'] ?? 'items.php');

// Scope keys/attribution to the bidder's pinned event when available.
$event = getCurrentEvent();
$event_id = $event ? (int)$event['id'] : 0;

$result = createCardSetupSession($user, $event_id, $return_path);

if (empty($result['success'])) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Could not start card setup']));
}

http_response_code(200);
echo json_encode(['status' => 'ok', 'url' => $result['url']]);

?>
