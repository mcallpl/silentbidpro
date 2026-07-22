<?php
// ============================================================
// API ENDPOINT: Start SaaS Subscription Checkout (WEB ONLY)
// POST /api/billing/create-checkout.php  { org_id, plan }
// Returns a Stripe Checkout (mode=subscription) URL to redirect the browser to.
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/admin-auth.php';
require_once __DIR__ . '/../../includes/admin-auth-middleware.php';
require_once __DIR__ . '/../../includes/billing.php';

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
$org_id = (int)($input['org_id'] ?? 0);
$plan = normalizePlan($input['plan'] ?? '');
// Default to the admin's own organization (organizer self-serve flow).
if (!$org_id) {
    $org_id = (int)($admin['organization_id'] ?? 0);
}
if (!$org_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'org_id required']));
}

// Only a super admin or a manager of this org may subscribe it.
requireAdminOrgAccess($org_id);

$base = rtrim(PUBLIC_SITE_URL, '/');
$result = createSubscriptionCheckout(
    $org_id, $plan,
    $base . '/billing.php?upgraded=1',
    $base . '/billing.php?canceled=1'
);

if (empty($result['success'])) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Could not start checkout']));
}

http_response_code(200);
echo json_encode(['status' => 'ok', 'url' => $result['url']]);
