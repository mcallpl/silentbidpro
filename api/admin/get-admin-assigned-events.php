<?php
// ============================================================
// API ENDPOINT: Get Events Assigned to Admin
// GET /api/admin/get-admin-assigned-events.php?admin_id=2
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/admin-auth.php';
require_once __DIR__ . '/../../includes/admin-auth-middleware.php';

header('Content-Type: application/json');

// Require authentication
$admin = getCurrentAdmin();
if (!$admin) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Not authenticated']));
}

// Get admin ID from query string (can be checking self or super admin checking others)
$target_admin_id = (int)($_GET['admin_id'] ?? 0);
if (!$target_admin_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Admin ID required']));
}

// Only allow checking own events or super admin can check anyone
if ($target_admin_id !== $admin['id'] && !$admin['is_super_admin']) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Access denied']));
}

// Get count of assigned events
$result = dbGetRow(
    "SELECT COUNT(*) as count FROM admin_events WHERE admin_id = ?",
    [(int)$target_admin_id]
);
$event_count = $result['count'] ?? 0;

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'event_count' => (int)$event_count
]);
?>
