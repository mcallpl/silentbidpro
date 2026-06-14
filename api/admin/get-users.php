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
$has_email_column = dbColumnExists('users', 'email');
$email_select = $has_email_column ? 'u.email' : "'' AS email";

// Build query
$where = "1=1";
$params = [];

if (!empty($search)) {
    $where .= $has_email_column
        ? " AND (u.full_name LIKE ? OR u.phone_number LIKE ? OR u.email LIKE ?)"
        : " AND (u.full_name LIKE ? OR u.phone_number LIKE ?)";
    $search_term = '%' . $search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    if ($has_email_column) {
        $params[] = $search_term;
    }
}

// Get total count
if (!empty($search)) {
    $count_params = ['%' . $search . '%', '%' . $search . '%'];
    $count_where = "full_name LIKE ? OR phone_number LIKE ?";
    if ($has_email_column) {
        $count_where .= " OR email LIKE ?";
        $count_params[] = '%' . $search . '%';
    }
    $total = dbGetRow("SELECT COUNT(*) as count FROM users WHERE {$count_where}", $count_params)['count'];
} else {
    $total = dbCount('users');
}

// Get users with aggregated data
// First get base user data with bid counts
$query = "SELECT
            u.id,
            u.full_name,
            {$email_select},
            u.phone_number,
            CONCAT(SUBSTR(u.phone_number, 1, 6), '...', SUBSTR(u.phone_number, -4)) as phone_display,
            COUNT(DISTINCT b.id) as bid_count,
            MAX(b.created_at) as last_bid_at
         FROM users u
         LEFT JOIN bids b ON u.id = b.user_id
         WHERE " . $where . "
         GROUP BY u.id, u.full_name, u.phone_number" . ($has_email_column ? ', u.email' : '') . "
         ORDER BY MAX(b.created_at) DESC
         LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;

$users = dbGetAll($query, $params);

// For each user, get items won and total spent
foreach ($users as &$user) {
    $user_wins = dbGetRow(
        "SELECT
            COUNT(DISTINCT i.id) as items_won,
            SUM(i.current_high_bid) as total_spent
         FROM items i
         WHERE i.is_closed = 1 AND i.current_high_bidder_id = ?",
        [(int)$user['id']]
    );

    $user['items_won'] = (int)($user_wins['items_won'] ?? 0);
    $user['total_spent'] = (float)($user_wins['total_spent'] ?? 0);
    $user['bid_count'] = (int)($user['bid_count'] ?? 0);
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
