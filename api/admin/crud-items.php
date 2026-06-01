<?php
// ============================================================
// ADMIN CRUD ENDPOINT: Items Management
// Requires super admin privileges
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/admin-accounts.php';
require_once __DIR__ . '/../../includes/session-manager.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    error_log('[ADMIN CRUD] ❌ Admin not logged in - Auth check failed');
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized. Admin session required.']));
}

error_log('[ADMIN CRUD] ✓ Admin authenticated - Proceeding with action: ' . ($action ?? 'none'));

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        handleListItems();
        break;
    case 'get':
        handleGetItem();
        break;
    case 'create':
        handleCreateItem();
        break;
    case 'update':
        handleUpdateItem();
        break;
    case 'delete':
        handleDeleteItem();
        break;
    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

function handleListItems() {
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;

    $items = dbGetAll(
        "SELECT id, item_number, title, starting_bid, current_high_bid, current_high_bidder_id,
                auction_start_time, auction_end_time, is_closed, created_at
         FROM items ORDER BY item_number ASC LIMIT ? OFFSET ?",
        [$limit, $offset]
    );

    $count_result = dbGetRow("SELECT COUNT(*) as count FROM items");
    $total = $count_result['count'] ?? 0;

    echo json_encode([
        'status' => 'ok',
        'data' => $items ?? [],
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function handleGetItem() {
    $item_id = (int)($_GET['item_id'] ?? 0);
    if (!$item_id) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'item_id required']));
    }

    $item = dbGetRow(
        "SELECT * FROM items WHERE id = ?",
        [$item_id]
    );

    if (!$item) {
        http_response_code(404);
        die(json_encode(['status' => 'error', 'message' => 'Item not found']));
    }

    echo json_encode(['status' => 'ok', 'data' => $item]);
}

function handleCreateItem() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Invalid JSON']));
    }

    $required = ['item_number', 'title', 'starting_bid', 'auction_end_time'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            die(json_encode(['status' => 'error', 'message' => "$field required"]));
        }
    }

    $item_id = dbInsert(
        "INSERT INTO items (item_number, title, description, starting_bid, min_increment,
                           auction_start_time, auction_end_time, is_closed)
         VALUES (?, ?, ?, ?, ?, ?, ?, 0)",
        [
            (int)$input['item_number'],
            $input['title'],
            $input['description'] ?? '',
            (float)$input['starting_bid'],
            (float)($input['min_increment'] ?? 5.00),
            $input['auction_start_time'] ?? date('Y-m-d H:i:s'),
            $input['auction_end_time']
        ]
    );

    echo json_encode([
        'status' => 'ok',
        'message' => 'Item created',
        'item_id' => $item_id
    ]);
}

function handleUpdateItem() {
    $input = json_decode(file_get_contents('php://input'), true);
    $item_id = (int)($_GET['item_id'] ?? 0);

    error_log('[ADMIN CRUD UPDATE] Item ID: ' . $item_id . ', Input: ' . json_encode($input));

    if (!$item_id || !$input) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'item_id and body required']));
    }

    // Verify item exists
    $item_check = dbGetRow("SELECT id FROM items WHERE id = ?", [$item_id]);
    if (!$item_check) {
        http_response_code(404);
        die(json_encode(['status' => 'error', 'message' => 'Item not found']));
    }

    $updates = [];
    $params = [];

    $updatable = ['title', 'description', 'image_url', 'fair_market_value', 'starting_bid', 'min_increment', 'buy_now_price', 'auction_start_time', 'auction_end_time', 'is_closed'];
    foreach ($updatable as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = ?";
            $params[] = $input[$field];
            error_log('[ADMIN CRUD UPDATE] Setting ' . $field . ' = ' . (is_array($input[$field]) ? json_encode($input[$field]) : $input[$field]));
        }
    }

    if (empty($updates)) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'No fields to update']));
    }

    $params[] = $item_id;

    $sql = "UPDATE items SET " . implode(', ', $updates) . " WHERE id = ?";
    error_log('[ADMIN CRUD UPDATE] SQL: ' . $sql . ' with params: ' . json_encode($params));

    $success = dbUpdate($sql, $params);

    if (!$success) {
        error_log('[ADMIN CRUD UPDATE] ❌ Update failed for item ' . $item_id);
        http_response_code(400);
    } else {
        error_log('[ADMIN CRUD UPDATE] ✓ Update successful for item ' . $item_id);
    }

    echo json_encode([
        'status' => $success ? 'ok' : 'error',
        'message' => $success ? 'Item updated' : 'Failed to update item'
    ]);
}

function handleDeleteItem() {
    $item_id = (int)($_GET['item_id'] ?? 0);
    if (!$item_id) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'item_id required']));
    }

    $success = dbDelete("DELETE FROM items WHERE id = ?", [$item_id]);

    echo json_encode([
        'status' => $success ? 'ok' : 'error',
        'message' => $success ? 'Item deleted' : 'Failed to delete item'
    ]);
}

?>
