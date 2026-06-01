<?php
// ============================================================
// API ENDPOINT: Get Paginated Users List
// GET /api/admin/get-users.php?page=1&limit=50&search=...
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
$search = $_GET['search'] ?? '';
$page = max(1, $page);
$limit = min($limit, 100);

$offset = ($page - 1) * $limit;

// Build query
$where = "1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (full_name LIKE ? OR phone_number LIKE ?)";
    $search_term = '%' . $search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
}

// Get total count
if (!empty($search)) {
    $total = dbGetRow(
        "SELECT COUNT(*) as count FROM users WHERE full_name LIKE ? OR phone_number LIKE ?",
        ['%' . $search . '%', '%' . $search . '%']
    )['count'];
} else {
    $total = dbCount('users');
}

// Get users with aggregated data
$query = "SELECT
            u.id,
            u.full_name,
            CONCAT(SUBSTR(u.phone_number, 1, 6), '...', SUBSTR(u.phone_number, -4)) as phone_display,
            COUNT(DISTINCT b.id) as bid_count,
            SUM(CASE WHEN b.item_id IN (SELECT id FROM items WHERE is_closed = 1 AND current_high_bidder_id = u.id) THEN 1 ELSE 0 END) as items_won,
            SUM(CASE WHEN b.item_id IN (SELECT id FROM items WHERE is_closed = 1 AND current_high_bidder_id = u.id) THEN (SELECT current_high_bid FROM items WHERE id = b.item_id) ELSE 0 END) as total_spent,
            MAX(b.created_at) as last_bid_at
         FROM users u
         LEFT JOIN bids b ON u.id = b.user_id
         WHERE " . $where . "
         GROUP BY u.id, u.full_name, u.phone_number
         ORDER BY MAX(b.created_at) DESC
         LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;

$users = dbGetAll($query, $params);

// Format response
foreach ($users as &$user) {
    $user['bid_count'] = (int)($user['bid_count'] ?? 0);
    $user['items_won'] = (int)($user['items_won'] ?? 0);
    $user['total_spent'] = (float)($user['total_spent'] ?? 0);
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'users' => $users,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => ceil($total / $limit)
    ]
]);

?>
