<?php
// ============================================================
// ADMIN CRUD ENDPOINT: Users Management
// Requires admin privileges
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/admin-accounts.php';
require_once __DIR__ . '/../../includes/session-manager.php';

header('Content-Type: application/json');

// User records are GLOBAL (a bidder's phone identity spans every event), so
// listing/reading/editing/deleting them is a super-admin function. Without this,
// any single-event admin could read every bidder's PII and delete any account.
$admin = requireAdminAuth();
if (empty($admin['is_super_admin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Super admin privileges required']);
    exit;
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
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 20))); // clamp 1..100
    $offset = ($page - 1) * $limit;

    $has_email_column = dbColumnExists('users', 'email');
    $email_select = $has_email_column ? 'email' : "'' AS email";

    $users = dbGetAll(
        "SELECT id, phone_number, full_name, {$email_select}, stripe_customer_id, created_at FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?",
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

    $has_email_column = dbColumnExists('users', 'email');
    $email_select = $has_email_column ? 'email' : "'' AS email";

    $user = dbGetRow(
        "SELECT id, phone_number, full_name, {$email_select}, stripe_customer_id, created_at, updated_at FROM users WHERE id = ?",
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

    $phone = normalizePhone($input['phone_number'] ?? '');
    $full_name = trim($input['full_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $stripe_customer_id = trim($input['stripe_customer_id'] ?? '');

    if (!$phone || !$full_name) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Valid phone number and full name are required']));
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Please enter a valid email address or leave it blank']));
    }

    $has_email_column = dbColumnExists('users', 'email');

    if ($has_email_column) {
        $user_id = dbInsert(
            "INSERT INTO users (phone_number, full_name, email, stripe_customer_id) VALUES (?, ?, ?, ?)",
            [$phone, $full_name, $email, $stripe_customer_id]
        );
    } else {
        $user_id = dbInsert(
            "INSERT INTO users (phone_number, full_name, stripe_customer_id) VALUES (?, ?, ?)",
            [$phone, $full_name, $stripe_customer_id]
        );
    }

    if (!$user_id) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Failed to create bidder. The phone number may already be in use.']));
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
        $full_name = trim($input['full_name']);
        if ($full_name === '') {
            http_response_code(400);
            die(json_encode(['status' => 'error', 'message' => 'Full name is required']));
        }
        $updates[] = "full_name = ?";
        $params[] = $full_name;
    }
    if (isset($input['phone_number'])) {
        $phone = normalizePhone($input['phone_number']);
        if (!$phone) {
            http_response_code(400);
            die(json_encode(['status' => 'error', 'message' => 'Please enter a valid phone number']));
        }
        $updates[] = "phone_number = ?";
        $params[] = $phone;
    }
    if (isset($input['email']) && dbColumnExists('users', 'email')) {
        $email = trim($input['email']);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            die(json_encode(['status' => 'error', 'message' => 'Please enter a valid email address or leave it blank']));
        }
        $updates[] = "email = ?";
        $params[] = $email;
    }
    if (isset($input['stripe_customer_id'])) {
        $updates[] = "stripe_customer_id = ?";
        $params[] = trim($input['stripe_customer_id']);
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

    $existing_user = dbGetRow("SELECT id FROM users WHERE id = ?", [$user_id]);
    if (!$existing_user) {
        http_response_code(404);
        die(json_encode(['status' => 'error', 'message' => 'Bidder not found']));
    }

    dbDelete("DELETE FROM sessions WHERE user_id = ?", [$user_id]);

    $success = dbDelete("DELETE FROM users WHERE id = ?", [$user_id]);

    if (!$success) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Failed to delete bidder. They may have linked auction activity that must be preserved.']));
    }

    echo json_encode(['status' => 'ok', 'message' => 'Bidder deleted']);
}

?>
