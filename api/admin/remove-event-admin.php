<?php
// ============================================================
// API ENDPOINT: Remove Admin from Event
// POST /api/admin/remove-event-admin.php
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

// Only super admins can remove admin assignments
if (!$admin['is_super_admin']) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Access denied']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid JSON']));
}

$event_id = (int)($input['event_id'] ?? 0);
$admin_id = (int)($input['admin_id'] ?? 0);

// Validate
if (!$event_id || !$admin_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Event ID and Admin ID required']));
}

// Delete the assignment
$success = dbDelete(
    "DELETE FROM admin_events WHERE admin_id = ? AND event_id = ?",
    [$admin_id, $event_id]
);

if (!$success) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Failed to remove assignment']));
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'message' => 'Admin removed from event'
]);
?>
