<?php
// ============================================================
// API ENDPOINT: Get Single Event Details
// GET /api/admin/get-event.php?id=1
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

// Get event ID from query string
$event_id = (int)($_GET['id'] ?? 0);
if (!$event_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Event ID required']));
}

// Check authorization
if (!$admin['is_super_admin']) {
    // Check if admin has access to this event
    $access = checkAdminEventAccess($admin['id'], $event_id);
    if (!$access) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Access denied']));
    }
}

// Fetch event
$event = dbGetRow(
    "SELECT id, name, organization_id, event_date, auction_start_time, auction_end_time,
            timezone, payment_mode, status, created_at, updated_at
     FROM events WHERE id = ?",
    [(int)$event_id]
);

if (!$event) {
    http_response_code(404);
    die(json_encode(['status' => 'error', 'message' => 'Event not found']));
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'event' => $event
]);
?>
