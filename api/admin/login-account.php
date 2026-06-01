<?php
// ============================================================
// API ENDPOINT: Admin Login (Username/Password)
// POST /api/admin/login-account.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/admin-accounts.php';

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

$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Username and password are required']));
}

// Authenticate admin
$admin = authenticateAdmin($username, $password);
if (!$admin) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Invalid username or password']));
}

// Login and set persistent session
loginAdmin($admin['id']);

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'message' => 'Logged in successfully',
    'admin' => [
        'id' => $admin['id'],
        'username' => $admin['username'],
        'full_name' => $admin['full_name']
    ]
]);

?>
