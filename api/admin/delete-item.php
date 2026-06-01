<?php
// ============================================================
// API ENDPOINT: Delete Item
// POST /api/admin/delete-item.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

requireAdminAuth();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid JSON']));
}

$item_id = (int)($input['item_id'] ?? 0);
if (!$item_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'item_id is required']));
}

// Verify item exists
$item = dbGetRow("SELECT id FROM items WHERE id = ?", [$item_id]);
if (!$item) {
    http_response_code(404);
    die(json_encode(['status' => 'error', 'message' => 'Item not found']));
}

// Delete item (cascade will handle bids)
$result = dbQuery("DELETE FROM items WHERE id = ?", [$item_id]);

if ($result) {
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Item deleted successfully'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to delete item'
    ]);
}

?>
