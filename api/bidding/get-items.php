<?php
// ============================================================
// API ENDPOINT: List Auction Items (bidder browse)
// GET /api/bidding/get-items.php?event=<slug>[&category=<id>][&q=<search>][&include_closed=1]
//
// JSON counterpart of items.php, for the native iOS app. Returns the open items
// of an event (plus a compact event/branding object to theme the app). Optional
// Bearer auth enriches each item with is_favorited / is_user_winning.
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/events.php';
require_once __DIR__ . '/../../includes/favorites.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

// Resolve the event: an explicit ?event=<slug> is the API contract; fall back to
// the session/active event so the endpoint still works without a slug.
$slug = trim($_GET['event'] ?? '');
$event = $slug !== '' ? getEventBySlug($slug) : getCurrentEvent();
if (!$event) {
    http_response_code(404);
    die(json_encode(['status' => 'error', 'message' => 'Auction not found']));
}
$event_id = (int)$event['id'];

// Optional authentication (enriches favorites / winning state).
$user = null;
$token = getSessionToken();
if ($token) {
    $user = validateSessionToken($token);
}
$user_id = $user ? (int)$user['id'] : 0;

$category_id = (int)($_GET['category'] ?? 0);
$search = trim($_GET['q'] ?? '');
$include_closed = !empty($_GET['include_closed']);

$has_event_columns = dbColumnExists('items', 'event_id') && dbColumnExists('items', 'close_time_override');
$has_category_columns = dbColumnExists('items', 'category_id') && dbTableExists('categories');
$has_favorites = favoritesAvailable();
$close_expr = $has_event_columns ? "COALESCE(i.close_time_override, i.auction_end_time)" : "i.auction_end_time";

$select = "i.id, i.item_number, i.title, i.description, i.image_url, i.fair_market_value,
        i.starting_bid, i.min_increment, i.buy_now_price, i.current_high_bid,
        i.current_high_bidder_id, i.auction_end_time, i.is_closed,
        {$close_expr} AS effective_close_time,
        TIMESTAMPDIFF(SECOND, NOW(), {$close_expr}) AS time_remaining,
        (SELECT COUNT(*) FROM bids b WHERE b.item_id = i.id) AS bid_count";
if ($has_category_columns) {
    $select .= ", i.category_id, c.name AS category_name";
}
if ($has_favorites && $user_id) {
    $select .= ", CASE WHEN f.id IS NULL THEN 0 ELSE 1 END AS is_favorited";
}

$sql = "SELECT {$select} FROM items i";
$params = [];
if ($has_category_columns) {
    $sql .= " LEFT JOIN categories c ON c.id = i.category_id";
}
if ($has_favorites && $user_id) {
    $sql .= " LEFT JOIN favorites f ON f.item_id = i.id AND f.user_id = ?";
    $params[] = $user_id;
}

$where = [];
if ($has_event_columns) {
    $where[] = "i.event_id = ?";
    $params[] = $event_id;
}
// Browse shows open, not-yet-expired items by default (matches items.php).
if (!$include_closed) {
    $where[] = "i.is_closed = 0";
    $where[] = "{$close_expr} > NOW()";
}
if ($has_category_columns && $category_id > 0) {
    $where[] = "i.category_id = ?";
    $params[] = $category_id;
}
if ($search !== '') {
    $where[] = "(i.title LIKE ? OR i.description LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$sql .= (!empty($where) ? " WHERE " . implode(" AND ", $where) : "")
      . " ORDER BY i.is_closed ASC, {$close_expr} ASC";

$rows = dbGetAll($sql, $params);

$items = [];
foreach ($rows as $r) {
    $high = (float)$r['current_high_bid'];
    $next_minimum = $high > 0 ? $high + (float)$r['min_increment'] : (float)$r['starting_bid'];
    $items[] = [
        'id' => (int)$r['id'],
        'item_number' => (int)$r['item_number'],
        'title' => $r['title'],
        'description' => $r['description'],
        'image_url' => $r['image_url'],
        'fair_market_value' => (float)$r['fair_market_value'],
        'starting_bid' => (float)$r['starting_bid'],
        'min_increment' => (float)$r['min_increment'],
        'buy_now_price' => $r['buy_now_price'] ? (float)$r['buy_now_price'] : null,
        'current_high_bid' => $high,
        'next_minimum' => round($next_minimum, 2),
        'bid_count' => (int)$r['bid_count'],
        'auction_end_time' => $r['effective_close_time'] ?? $r['auction_end_time'],
        'time_remaining_ms' => max(0, (int)$r['time_remaining'] * 1000),
        'is_closed' => (bool)$r['is_closed'],
        'is_user_winning' => $user_id && (int)$r['current_high_bidder_id'] === $user_id,
        'is_favorited' => !empty($r['is_favorited']),
        'category_id' => isset($r['category_id']) ? (int)$r['category_id'] : null,
        'category_name' => $r['category_name'] ?? null,
    ];
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'event' => [
        'id' => $event_id,
        'slug' => $event['slug'] ?? null,
        'name' => $event['name'] ?? 'Auction',
        'organization_name' => $event['organization_name'] ?? null,
        'brand_primary' => $event['brand_primary'] ?? null,
        'brand_accent' => $event['brand_accent'] ?? null,
        'logo_url' => $event['logo_url'] ?? null,
        'event_date' => $event['event_date'] ?? null,
    ],
    'count' => count($items),
    'items' => $items,
]);
