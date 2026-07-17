<?php
// ============================================================
// API ENDPOINT: Create a Stripe session for a direct donation
// POST /api/checkout/create-donation-session.php  { amount, event_id }
// Open to everyone — donors don't have to be bidders. The donor's
// name/email are collected by Stripe Checkout itself.
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/stripe-utils.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$amount = round((float)($input['amount'] ?? 0), 2);
$event_id = (int)($input['event_id'] ?? 0);

if ($amount < 1 || $amount > 25000) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Please enter a donation between $1 and $25,000.']));
}

$event = $event_id ? dbGetRow(
    "SELECT e.id, e.name, e.donations_enabled, o.name AS organization_name
     FROM events e JOIN organizations o ON o.id = e.organization_id
     WHERE e.id = ?",
    [$event_id]
) : null;

if (!$event) {
    http_response_code(404);
    die(json_encode(['status' => 'error', 'message' => 'Event not found']));
}
if ((int)$event['donations_enabled'] !== 1) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Donations are not enabled for this event.']));
}

// Optional signed-in user for attribution.
$viewer = getCurrentUser();

// TEST MODE: same $1 rule as auction charges.
$charge_amount = $amount;
$test_note = '';
if (defined('TEST_CHARGE_DOLLAR') && TEST_CHARGE_DOLLAR) {
    $charge_amount = 1.00;
    $test_note = ' (test charge — $1)';
}

$donation_id = dbInsert(
    "INSERT INTO donations (event_id, user_id, amount, status, created_at)
     VALUES (?, ?, ?, 'pending', NOW())",
    [$event_id, $viewer ? (int)$viewer['id'] : null, $charge_amount]
);
if (!$donation_id) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Could not record donation']));
}

$keys = getEventStripeKeys($event_id);
$session_data = [
    'payment_method_types[0]' => 'card',
    'mode' => 'payment',
    'submit_type' => 'donate',
    'line_items[0][price_data][currency]' => 'usd',
    'line_items[0][price_data][product_data][name]' => 'Donation — ' . $event['organization_name'],
    'line_items[0][price_data][product_data][description]' => $event['name'] . $test_note,
    'line_items[0][price_data][unit_amount]' => (int)round($charge_amount * 100),
    'line_items[0][quantity]' => '1',
    'success_url' => APP_DOMAIN . '/donate.php?event=' . $event_id . '&thanks=1',
    'cancel_url' => APP_DOMAIN . '/donate.php?event=' . $event_id,
    'metadata[purpose]' => 'donation',
    'metadata[donation_id]' => $donation_id,
    'metadata[event_id]' => $event_id,
    'metadata[organization]' => $event['organization_name']
];

$response = callStripeAPI('/v1/checkout/sessions', $session_data, 'POST', $keys['secret_key']);
if (empty($response['id']) || empty($response['url'])) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => $response['error']['message'] ?? 'Could not start donation checkout']));
}

dbUpdate(
    "UPDATE donations SET stripe_checkout_session_id = ? WHERE id = ?",
    [$response['id'], (int)$donation_id]
);

http_response_code(200);
echo json_encode(['status' => 'ok', 'url' => $response['url']]);

?>
