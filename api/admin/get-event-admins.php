<?php
// ============================================================
// API ENDPOINT: Get Admins Assigned to Event
// GET /api/admin/get-event-admins.php?event_id=1
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

// Only super admins can view admin assignments
if (!$admin['is_super_admin']) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Access denied']));
}

// Get event ID from query string
$event_id = (int)($_GET['event_id'] ?? 0);
if (!$event_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Event ID required']));
}

// Fetch admins assigned to this event
$assignments = dbGetAll(
    "SELECT ae.admin_id, ae.role, a.username as admin_username, a.full_name as admin_name
     FROM admin_events ae
     JOIN admin_accounts a ON ae.admin_id = a.id
     WHERE ae.event_id = ?
     ORDER BY a.full_name",
    [(int)$event_id]
);

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'assignments' => $assignments ?: []
]);
?>
