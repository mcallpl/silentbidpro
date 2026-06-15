<?php
// ============================================================
// API ENDPOINT: Get All Events
// GET /api/admin/get-events.php
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

// Super admins can see all events, org/event admins see their events
$events = [];

if ($admin['is_super_admin']) {
    // Super admin sees all events with organization names
    $events = dbGetAll(
        "SELECT e.id, e.name, e.organization_id, o.name AS organization_name,
                e.is_active, COUNT(DISTINCT i.id) AS item_count
         FROM events e
         LEFT JOIN organizations o ON e.organization_id = o.id
         LEFT JOIN items i ON e.id = i.event_id
         GROUP BY e.id
         ORDER BY e.created_at DESC"
    );
} else {
    // Non-super admins see events they have access to
    $events = dbGetAll(
        "SELECT DISTINCT e.id, e.name, e.organization_id, o.name AS organization_name,
                e.is_active, COUNT(DISTINCT i.id) AS item_count
         FROM events e
         LEFT JOIN organizations o ON e.organization_id = o.id
         LEFT JOIN items i ON e.id = i.event_id
         LEFT JOIN admin_organizations ao ON e.organization_id = ao.organization_id
         LEFT JOIN admin_events ae ON e.id = ae.event_id
         WHERE ao.admin_id = ? OR ae.admin_id = ?
         GROUP BY e.id
         ORDER BY e.created_at DESC",
        [(int)$admin['id'], (int)$admin['id']]
    );
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'events' => $events ?: []
]);
?>
