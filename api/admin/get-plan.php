<?php
// ============================================================
// API ENDPOINT: Get Organization Plan & Capabilities
// GET /api/admin/get-plan.php?org_id=<id>   (or ?event_id=<id>)
//
// Returns the org's plan + feature capabilities so the web UI / iOS app can
// adjust what they show. Returns NO pricing or purchase links — subscriptions
// are sold on the web only (keeps the iOS app clear of Apple's IAP rule).
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/admin-auth.php';
require_once __DIR__ . '/../../includes/admin-auth-middleware.php';
require_once __DIR__ . '/../../includes/plans.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$admin = getCurrentAdmin();
if (!$admin) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Not authenticated']));
}

$org_id = (int)($_GET['org_id'] ?? 0);
$event_id = (int)($_GET['event_id'] ?? 0);
if (!$org_id && $event_id) {
    $org_id = orgIdForEvent($event_id);
}
if (!$org_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'org_id or event_id required']));
}

// Authorization: super admin sees any org; others must have access to it.
if (empty($admin['is_super_admin'])) {
    $access = checkAdminOrgAccess($admin['id'], $org_id);
    if (!$access) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Access denied']));
    }
}

$plan = getOrgPlan($org_id);
$features = planFeatures($plan);
$limit = $features['max_active_events'];
$active = orgActiveEventCount($org_id);

// Capabilities the UI/app cares about — booleans + limits only, NO price.
$capabilities = [
    'custom_branding'   => (bool)$features['custom_branding'],
    'csv_export'        => (bool)$features['csv_export'],
    'bigscreen_display' => (bool)$features['bigscreen_display'],
    'multi_chapter'     => (bool)$features['multi_chapter'],
    'api_access'        => (bool)$features['api_access'],
    'sso'               => (bool)$features['sso'],
    'priority_support'  => (bool)$features['priority_support'],
    'max_active_events' => $limit, // null = unlimited
];

http_response_code(200);
echo json_encode([
    'status'   => 'ok',
    'org_id'   => $org_id,
    'plan'     => $plan,
    'plan_label' => $features['label'],
    'active_events'    => $active,
    'active_events_remaining' => $limit === null ? null : max(0, $limit - $active),
    'capabilities'     => $capabilities,
]);
