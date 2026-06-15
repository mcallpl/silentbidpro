<?php
// ============================================================
// API ENDPOINT: Assign Admins to Event
// POST /api/admin/assign-event-admins.php
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

// Only super admins can assign admins
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
$admin_ids = $input['admin_ids'] ?? [];
$role = $input['role'] ?? '';

// Validate
if (!$event_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Event ID required']));
}

if (empty($admin_ids) || !is_array($admin_ids)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Admin IDs required']));
}

if (!in_array($role, ['viewer', 'manager'])) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid role']));
}

// Verify event exists
$event = dbGetRow("SELECT id FROM events WHERE id = ?", [(int)$event_id]);
if (!$event) {
    http_response_code(404);
    die(json_encode(['status' => 'error', 'message' => 'Event not found']));
}

// Assign each admin
$assigned_count = 0;
foreach ($admin_ids as $admin_id) {
    $admin_id = (int)$admin_id;

    // Verify admin exists and is not super admin
    $target_admin = dbGetRow(
        "SELECT id, is_super_admin FROM admin_accounts WHERE id = ?",
        [$admin_id]
    );

    if (!$target_admin || $target_admin['is_super_admin']) {
        continue; // Skip super admins and non-existent admins
    }

    // Insert or update assignment
    dbInsert(
        "INSERT INTO admin_events (admin_id, event_id, role)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE role = VALUES(role)",
        [$admin_id, (int)$event_id, $role]
    );

    $assigned_count++;
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'message' => "Assigned $assigned_count admin(s) to event",
    'assigned_count' => $assigned_count
]);
?>
