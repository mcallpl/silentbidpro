<?php
// ============================================================
// ADMIN CRUD ENDPOINT: Users Management
// Requires super admin privileges
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/admin-accounts.php';
require_once __DIR__ . '/../../includes/session-manager.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized. Admin session required.']));
}

// For super admin checks, we'd need to store admin_id in session
// For now, check via cookie (simplified)
$token = getSessionCookie(ADMIN_SESSION_COOKIE_NAME);
if (!$token) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Super admin access required']));
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        handleListUsers();
        break;
    case 'get':
        handleGetUser();
        break;
    case 'create':
        handleCreateUser();
        break;
    case 'update':
        handleUpdateUser();
        break;
    case 'delete':
        handleDeleteUser();
        break;
    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

function handleListUsers() {
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;

    $users = dbGetAll(
        "SELECT id, phone_number, full_name, stripe_customer_id, created_at FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?",
        [$limit, $offset]
    );

    $count_result = dbGetRow("SELECT COUNT(*) as count FROM users");
    $total = $count_result['count'] ?? 0;

    echo json_encode([
        'status' => 'ok',
        'data' => $users ?? [],
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function handleGetUser() {
    $user_id = (int)($_GET['user_id'] ?? 0);
    if (!$user_id) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'user_id required']));
    }

    $user = dbGetRow(
        "SELECT id, phone_number, full_name, stripe_customer_id, created_at, updated_at FROM users WHERE id = ?",
        [$user_id]
    );

    if (!$user) {
        http_response_code(404);
        die(json_encode(['status' => 'error', 'message' => 'User not found']));
    }

    echo json_encode(['status' => 'ok', 'data' => $user]);
}

function handleCreateUser() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Invalid JSON']));
    }

    $phone = $input['phone_number'] ?? '';
    $full_name = $input['full_name'] ?? '';

    if (!$phone || !$full_name) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'phone_number and full_name required']));
    }

    $user_id = dbInsert(
        "INSERT INTO users (phone_number, full_name) VALUES (?, ?)",
        [$phone, $full_name]
    );

    if (!$user_id) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Failed to create user (duplicate phone?)']));
    }

    echo json_encode([
        'status' => 'ok',
        'message' => 'User created',
        'user_id' => $user_id
    ]);
}

function handleUpdateUser() {
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = (int)($_GET['user_id'] ?? 0);

    if (!$user_id || !$input) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'user_id and body required']));
    }

    $updates = [];
    $params = [];

    if (isset($input['full_name'])) {
        $updates[] = "full_name = ?";
        $params[] = $input['full_name'];
    }
    if (isset($input['stripe_customer_id'])) {
        $updates[] = "stripe_customer_id = ?";
        $params[] = $input['stripe_customer_id'];
    }

    if (empty($updates)) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'No fields to update']));
    }

    $params[] = $user_id;
    $success = dbUpdate(
        "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?",
        $params
    );

    echo json_encode([
        'status' => $success ? 'ok' : 'error',
        'message' => $success ? 'User updated' : 'Failed to update user'
    ]);
}

function handleDeleteUser() {
    $user_id = (int)($_GET['user_id'] ?? 0);
    if (!$user_id) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'user_id required']));
    }

    $success = dbDelete("DELETE FROM users WHERE id = ?", [$user_id]);

    echo json_encode([
        'status' => $success ? 'ok' : 'error',
        'message' => $success ? 'User deleted' : 'Failed to delete user'
    ]);
}

?>
