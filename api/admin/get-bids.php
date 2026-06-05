<?php
// ============================================================
// API ENDPOINT: Get Paginated Bids List
// GET /api/admin/get-bids.php?page=1&limit=50
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
$filter_type = $_GET['filter'] ?? 'all'; // all, active, winning
$item_id = (int)($_GET['item_id'] ?? 0);
$sort_by = $_GET['sort_by'] ?? 'created_at'; // column to sort by
$sort_order = $_GET['sort_order'] ?? 'DESC'; // ASC or DESC

$page = max(1, $page);
$limit = min($limit, 100);
$sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

// Whitelist sort columns
$allowed_sorts = ['b.id', 'i.title', 'u.full_name', 'b.bid_amount', 'i.current_high_bid', 'b.created_at'];
$sort_by = in_array($sort_by, $allowed_sorts) ? $sort_by : 'b.created_at';

$offset = ($page - 1) * $limit;

// Build WHERE clause
$where = "1=1";
$params = [];

// Filter by bid type
if ($filter_type === 'active') {
    $where .= " AND i.is_closed = 0";
} elseif ($filter_type === 'winning') {
    $where .= " AND b.bid_amount = i.current_high_bid AND i.is_closed = 1";
}

// Filter by item if specified
if ($item_id > 0) {
    $where .= " AND b.item_id = ?";
    $params[] = $item_id;
}

// Get total count
$count_query = "SELECT COUNT(*) FROM bids b JOIN items i ON i.id = b.item_id WHERE " . $where;
$total = dbGetValue($count_query, $params);

// Get bids with item and user details
$query = "SELECT
        b.id,
        b.item_id,
        b.user_id,
        b.bid_amount,
        b.created_at,
        i.title as item_title,
        i.item_number,
        i.current_high_bid,
        i.is_closed,
        u.full_name,
        u.phone_number,
        (b.bid_amount = i.current_high_bid AND i.is_closed = 1) as is_winning_bid
     FROM bids b
     JOIN items i ON i.id = b.item_id
     JOIN users u ON u.id = b.user_id
     WHERE " . $where . "
     ORDER BY " . $sort_by . " " . $sort_order . "
     LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;

$bids = dbGetAll($query, $params);

// Format response
foreach ($bids as &$bid) {
    $bid['bid_amount'] = (float)$bid['bid_amount'];
    $bid['current_high_bid'] = (float)$bid['current_high_bid'];
    $bid['is_closed'] = (bool)$bid['is_closed'];
    $bid['is_winning_bid'] = (bool)$bid['is_winning_bid'];
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'bids' => $bids,
    'filter' => $filter_type,
    'item_id' => $item_id,
    'sort_by' => $sort_by,
    'sort_order' => $sort_order,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => ceil($total / $limit)
    ]
]);

?>
