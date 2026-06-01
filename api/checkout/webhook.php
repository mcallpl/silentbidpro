<?php
// ============================================================
// API ENDPOINT: Stripe Webhook Handler
// POST /api/checkout/webhook.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/stripe-utils.php';

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

// Get raw payload and signature
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($payload)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Missing payload']));
}

// Parse event
$event = json_decode($payload, true);
if (!$event) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']));
}

// Verify webhook signature
if ($signature && STRIPE_WEBHOOK_SECRET) {
    $expected_signature = hash_hmac('sha256', $payload, STRIPE_WEBHOOK_SECRET, false);
    if (!hash_equals($expected_signature, $signature)) {
        http_response_code(401);
        die(json_encode(['status' => 'error', 'message' => 'Invalid signature']));
    }
}

$event_type = $event['type'] ?? '';

// Route based on event type
switch ($event_type) {
    case 'checkout.session.completed':
        $session = $event['data']['object'] ?? [];
        if (processCheckoutCompleted($session)) {
            http_response_code(200);
            echo json_encode(['status' => 'ok', 'message' => 'Payment processed', 'event_type' => $event_type]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Payment processing failed']);
        }
        break;

    case 'charge.failed':
        $charge = $event['data']['object'] ?? [];
        if (!empty($charge['payment_intent'])) {
            dbUpdate(
                "UPDATE transactions SET status = ? WHERE stripe_payment_intent_id = ?",
                ['failed', $charge['payment_intent']]
            );
        }
        dbInsert(
            "INSERT INTO audit_log (event_type, description, created_at)
             VALUES (?, ?, NOW())",
            ['PAYMENT_FAILED', 'Payment failed for charge: ' . ($charge['id'] ?? 'unknown')]
        );
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Charge failed recorded']);
        break;

    default:
        // Acknowledge other events
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Event received', 'event_type' => $event_type]);
}

?>
