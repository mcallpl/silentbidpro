<?php
// ============================================================
// API ENDPOINT: Stripe Billing Webhook (SaaS subscriptions)
// POST /api/billing/webhook.php
// Verifies the signature against the DEDICATED billing webhook secret, then
// drives organizations.plan/subscription_status from the subscription lifecycle.
// (Separate endpoint + secret from the auction-payment webhook.)
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/billing.php'; // pulls in stripe-utils (verify) + plans

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($payload)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Missing payload']));
}

// Fail-closed signature check against the billing endpoint secret.
if (!STRIPE_BILLING_WEBHOOK_SECRET
    || empty($signature)
    || !verifyStripeSignatureHeader($payload, $signature, STRIPE_BILLING_WEBHOOK_SECRET)) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Invalid or missing signature']));
}

$event = json_decode($payload, true);
if (!$event || empty($event['type'])) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid event']));
}

try {
    $handled = applySubscriptionEvent($event);
} catch (\Throwable $e) {
    error_log('[BILLING WEBHOOK] ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Processing error']));
}

http_response_code(200);
echo json_encode(['status' => 'ok', 'handled' => $handled, 'event_type' => $event['type']]);
