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

// Get event_id from session metadata (for event-specific Stripe verification)
$event_id = 0;
if (($event['type'] ?? '') === 'checkout.session.completed') {
    $session = $event['data']['object'] ?? [];
    $event_id = (int)($session['metadata']['event_id'] ?? 0);
}

// SECURITY: webhook signature verification is MANDATORY and fail-closed.
// Previously this ran only `if ($signature && ...)`, so an attacker who simply
// omitted the Stripe-Signature header skipped verification entirely and could
// POST a forged "checkout.session.completed" to mark any item paid. Now a
// missing signature, a missing/misconfigured secret, or a failed check all
// reject the request before any processing.
if (empty($signature) || !verifyStripeSignature($payload, $signature, $event_id)) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Invalid or missing signature']));
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

    case 'payment_intent.succeeded':
        // Backup path for off-session auto-charges (normally marked paid
        // synchronously by the auction closer). Idempotent.
        processPaymentIntentSucceeded($event['data']['object'] ?? []);
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Payment intent recorded']);
        break;

    case 'charge.failed':
    case 'payment_intent.payment_failed':
        $obj = $event['data']['object'] ?? [];
        // charge.failed carries payment_intent; payment_intent.* IS the intent (id).
        $pi = $obj['payment_intent'] ?? ($event_type === 'payment_intent.payment_failed' ? ($obj['id'] ?? '') : '');
        if (!empty($pi)) {
            // Never overwrite a genuinely-paid transaction. Stripe can deliver a
            // charge.failed (first card declined) AFTER the checkout.session.completed
            // for a successful retry in the same session; without this guard that
            // late failure would flip a paid row to "failed".
            dbUpdate(
                "UPDATE transactions SET status = ? WHERE stripe_payment_intent_id = ? AND status != 'paid'",
                ['failed', $pi]
            );
        }
        dbInsert(
            "INSERT INTO audit_log (event_type, description, created_at)
             VALUES (?, ?, NOW())",
            ['PAYMENT_FAILED', 'Payment failed (' . $event_type . '): ' . ($obj['id'] ?? 'unknown')]
        );
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Payment failure recorded']);
        break;

    case 'checkout.session.expired':
        // Bidder abandoned Stripe Checkout — move the pending transaction to
        // cancelled so it doesn't linger as "pending" forever.
        $session = $event['data']['object'] ?? [];
        if (!empty($session['id'])) {
            dbUpdate(
                "UPDATE transactions SET status = ? WHERE stripe_checkout_session_id = ? AND status = 'pending'",
                ['cancelled', $session['id']]
            );
        }
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Checkout expiry recorded']);
        break;

    default:
        // Acknowledge other events
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Event received', 'event_type' => $event_type]);
}

?>
