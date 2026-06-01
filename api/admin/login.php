<?php
// ============================================================
// API ENDPOINT: Admin Login
// POST /api/admin/login.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/admin-auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid JSON']));
}

$token = $input['token'] ?? '';

if (empty($token)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Token is required']));
}

if (!validateAdminToken($token)) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Invalid admin token']));
}

setAdminCookie($token);

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'message' => 'Logged in successfully'
]);

?>
