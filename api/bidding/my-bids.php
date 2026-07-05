<?php
// ============================================================
// API ENDPOINT: My Bids (bidder's items + status)
// GET /api/bidding/my-bids.php[?event=<slug>]
// JSON counterpart of my-bids.php for the iOS app. Returns the authed user's
// bid items (with winning/outbid/won/lost/paid status) and their watchlist.
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

$user = requireAuth();
$user_id = (int)$user['id'];

$has_event_columns = dbColumnExists('items', 'event_id') && dbColumnExists('items', 'close_time_override');
$close_expr = $has_event_columns ? "COALESCE(i.close_time_override, i.auction_end_time)" : "i.auction_end_time";

// Optional event scope.
$slug = trim($_GET['event'] ?? '');
$event_filter = '';
$event_params = [];
if ($has_event_columns && $slug !== '') {
    $ev = getEventBySlug($slug);
    if ($ev) { $event_filter = ' AND i.event_id = ?'; $event_params[] = (int)$ev['id']; }
}

$rows = dbGetAll(
    "SELECT i.id, i.item_number, i.title, i.image_url, i.min_increment,
            i.current_high_bid, i.current_high_bidder_id, i.is_closed,
            {$close_expr} AS effective_close_time,
            TIMESTAMPDIFF(SECOND, NOW(), {$close_expr}) AS time_remaining,
            MAX(b.bid_amount) AS my_highest_bid,
            MAX(b.max_bid_amount) AS my_max_bid,
            t.status AS transaction_status
     FROM items i
     JOIN bids b ON b.item_id = i.id AND b.user_id = ?
     LEFT JOIN transactions t ON t.id = (
         SELECT tx.id FROM transactions tx
         WHERE tx.item_id = i.id AND tx.user_id = ?
         ORDER BY tx.created_at DESC LIMIT 1
     )
     WHERE 1=1 {$event_filter}
     GROUP BY i.id, i.item_number, i.title, i.image_url, i.min_increment,
              i.current_high_bid, i.current_high_bidder_id, i.is_closed,
              effective_close_time, time_remaining, t.status
     ORDER BY i.is_closed ASC, effective_close_time ASC",
    array_merge([$user_id, $user_id], $event_params)
);

$summary = ['winning' => 0, 'outbid' => 0, 'won' => 0, 'unpaid' => 0];
$bids = [];
foreach ($rows as $r) {
    $isWinner = (int)($r['current_high_bidder_id'] ?? 0) === $user_id;
    $isClosed = (bool)$r['is_closed'];
    $isPaid = ($r['transaction_status'] ?? '') === 'paid';
    if ($isClosed) {
        $status = $isWinner ? ($isPaid ? 'paid' : 'won') : 'lost';
    } else {
        $status = $isWinner ? 'winning' : 'outbid';
    }
    if ($status === 'winning') $summary['winning']++;
    if ($status === 'outbid')  $summary['outbid']++;
    if ($status === 'won')     { $summary['won']++; if (!$isPaid) $summary['unpaid']++; }

    $bids[] = [
        'id' => (int)$r['id'],
        'item_number' => (int)$r['item_number'],
        'title' => $r['title'],
        'image_url' => $r['image_url'],
        'my_highest_bid' => (float)$r['my_highest_bid'],
        'my_max_bid' => $r['my_max_bid'] !== null ? (float)$r['my_max_bid'] : null,
        'current_high_bid' => (float)$r['current_high_bid'],
        'is_closed' => $isClosed,
        'is_winning' => $isWinner,
        'status' => $status,
        'transaction_status' => $r['transaction_status'],
        'time_remaining_ms' => max(0, (int)$r['time_remaining'] * 1000),
    ];
}

// Watchlist (favorited items not necessarily bid on).
$watched = [];
if (favoritesAvailable()) {
    $wrows = dbGetAll(
        "SELECT i.id, i.item_number, i.title, i.image_url, i.current_high_bid,
                i.starting_bid, i.is_closed,
                TIMESTAMPDIFF(SECOND, NOW(), {$close_expr}) AS time_remaining
         FROM favorites f
         JOIN items i ON i.id = f.item_id
         WHERE f.user_id = ? {$event_filter}
         ORDER BY i.is_closed ASC, {$close_expr} ASC",
        array_merge([$user_id], $event_params)
    );
    foreach ($wrows as $r) {
        $high = (float)$r['current_high_bid'];
        $watched[] = [
            'id' => (int)$r['id'],
            'item_number' => (int)$r['item_number'],
            'title' => $r['title'],
            'image_url' => $r['image_url'],
            'current_high_bid' => $high,
            'display_price' => $high > 0 ? $high : (float)$r['starting_bid'],
            'is_closed' => (bool)$r['is_closed'],
            'time_remaining_ms' => max(0, (int)$r['time_remaining'] * 1000),
        ];
    }
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'summary' => $summary,
    'bids' => $bids,
    'watched' => $watched,
]);
