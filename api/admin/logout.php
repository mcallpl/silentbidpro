<?php
// ============================================================
// API ENDPOINT: Admin Logout
// POST /api/admin/logout.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/admin-auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

clearAdminCookie();

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'message' => 'Logged out successfully'
]);

?>
