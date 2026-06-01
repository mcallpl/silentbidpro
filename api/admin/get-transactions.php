<?php
// ============================================================
// API ENDPOINT: Get Transactions List
// GET /api/admin/get-transactions.php?page=1&limit=50&status=pending
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

requireAdminAuth();

$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 50);
$status = $_GET['status'] ?? '';
$page = max(1, $page);
$limit = min($limit, 100);

$offset = ($page - 1) * $limit;

// Build query
$where = "1=1";
$params = [];

if (!empty($status)) {
    $where .= " AND t.status = ?";
    $params[] = $status;
}

// Get total count
$count_query = "SELECT COUNT(*) as count FROM transactions t WHERE " . $where;
if (!empty($params)) {
    $total = dbGetRow($count_query, $params)['count'];
} else {
    $total = dbCount('transactions');
}

// Get transactions
$query = "SELECT
            t.id,
            t.user_id,
            t.item_id,
            t.amount,
            t.status,
            t.stripe_payment_intent_id,
            t.created_at,
            i.title as item_title,
            u.full_name as winner_name
         FROM transactions t
         JOIN items i ON t.item_id = i.id
         JOIN users u ON t.user_id = u.id
         WHERE " . $where . "
         ORDER BY t.created_at DESC
         LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;

$transactions = dbGetAll($query, $params);

// Format response
foreach ($transactions as &$t) {
    $t['amount'] = (float)$t['amount'];
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'transactions' => $transactions,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => ceil($total / $limit)
    ]
]);

?>
