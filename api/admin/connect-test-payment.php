<?php
// ============================================================
// API: $1 payout test — prove the money path end to end
// POST /api/admin/connect-test-payment.php   { org_id? }
//
// Creates a REAL $1.00 Stripe Checkout routed EXACTLY like a live
// event payment for this organization:
//   - Connect account ready  -> platform key + destination to their acct
//   - Event with own keys    -> session created on THEIR key
// The organizer pays it with their own card; when it lands in their
// balance, the connection is proven with real money, not a promise.
// (They can refund the $1 from their Stripe dashboard afterwards.)
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/admin-auth.php';
require_once __DIR__ . '/../../includes/admin-auth-middleware.php';
require_once __DIR__ . '/../../includes/connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}
$admin = getCurrentAdmin();
if (!$admin) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Not authenticated']));
}
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$org_id = (int)($input['org_id'] ?? 0) ?: (int)($admin['organization_id'] ?? 0);
if (!$org_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'org_id required']));
}
requireAdminOrgAccess($org_id);

$base = rtrim(PUBLIC_SITE_URL, '/');
$org_name = (string)dbGetValue("SELECT name FROM organizations WHERE id = ?", [$org_id]);

// Route selection — the SAME precedence a real payment uses.
$secret_key = '';           // '' = platform key
$extra_pi = [];
$mode = null;
if (orgConnectReady($org_id)) {
    $acct = orgConnectAccount($org_id);
    $extra_pi = connectPaymentParams($acct['stripe_account_id']);
    $mode = 'connect';
} else {
    $own = dbGetRow(
        "SELECT es.stripe_key_secret FROM event_settings es
         JOIN events e ON e.id = es.event_id
         WHERE e.organization_id = ? AND es.stripe_key_secret IS NOT NULL AND es.stripe_key_secret <> ''
         ORDER BY es.updated_at DESC LIMIT 1", [$org_id]);
    if ($own) { $secret_key = $own['stripe_key_secret']; $mode = 'byo'; }
}
if (!$mode) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Connect a payment account first — then run the $1 test.']));
}

$session_data = [
    'mode' => 'payment',
    'line_items' => [[
        'quantity' => 1,
        'price_data' => [
            'currency' => 'usd',
            'unit_amount' => 100,
            'product_data' => ['name' => '$1 payout test — ' . ($org_name ?: 'your organization')],
        ],
    ]],
    'metadata' => ['type' => 'payout_test', 'org_id' => (string)$org_id, 'route' => $mode],
    'payment_intent_data' => array_merge(
        ['description' => 'Silent Bid Pro $1 payout test (org ' . $org_id . ')'],
        $extra_pi
    ),
    'success_url' => $base . '/command-center.php?paytest={CHECKOUT_SESSION_ID}&paytest_org=' . $org_id . '#settings',
    'cancel_url'  => $base . '/command-center.php?paytest=canceled#settings',
];

$session = callStripeAPI('/v1/checkout/sessions', $session_data, 'POST', $secret_key);
if (empty($session['url'])) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => $session['error']['message'] ?? 'Could not create the test payment']));
}

echo json_encode(['status' => 'ok', 'url' => $session['url'], 'route' => $mode]);
