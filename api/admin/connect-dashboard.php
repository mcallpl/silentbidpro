<?php
// ============================================================
// API: One-click Stripe Express dashboard for the org's connected
// account (balance, payouts, bank details — no password juggling).
// POST /api/admin/connect-dashboard.php   { org_id? }
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

$url = connectDashboardLink($org_id);
if (!$url) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'No connected Stripe account for this organization yet']));
}
echo json_encode(['status' => 'ok', 'url' => $url]);
