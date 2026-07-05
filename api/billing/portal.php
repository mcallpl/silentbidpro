<?php
// ============================================================
// API ENDPOINT: Open Stripe Billing Portal (WEB ONLY)
// POST /api/billing/portal.php  { org_id }
// Returns a Stripe Billing Portal URL so an org can manage/cancel its plan.
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
if (!$org_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'org_id required']));
}

requireAdminOrgAccess($org_id);

$result = createBillingPortalSession($org_id, rtrim(PUBLIC_SITE_URL, '/') . '/billing.php');
if (empty($result['success'])) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Could not open billing portal']));
}

http_response_code(200);
echo json_encode(['status' => 'ok', 'url' => $result['url']]);
