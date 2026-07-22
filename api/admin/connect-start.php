<?php
// ============================================================
// API: Start Stripe Connect Express onboarding for an organization
// POST /api/admin/connect-start.php   { org_id? }
// Returns { status:'ok', url } — redirect the browser to Stripe's
// hosted onboarding (identity + bank account, ~5 minutes).
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

// Super admin, or a manager of this org.
requireAdminOrgAccess($org_id);

if (!connectPlatformAvailable()) {
    http_response_code(503);
    die(json_encode([
        'status' => 'error',
        'code' => 'connect_unavailable',
        'message' => 'One-click Stripe connection is not activated yet. Use "your own Stripe account" below, or check back soon.',
    ]));
}

$base = rtrim(PUBLIC_SITE_URL, '/');
$result = startConnectOnboarding(
    $org_id,
    $base . '/connect-return.php?org=' . $org_id,
    $base . '/connect-return.php?org=' . $org_id . '&refresh=1'
);

if (empty($result['success'])) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => $result['error']]));
}

echo json_encode(['status' => 'ok', 'url' => $result['url']]);
