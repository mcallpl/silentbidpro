<?php
// ============================================================
// API ENDPOINT: Get Live Bid Feed
// GET /api/bidding/get-live-feed.php?id=ITEM_ID&limit=20
// CRITICAL: Real-time bid sync - must not be cached
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/bidding.php';

// CRITICAL: Prevent all caching - users must see current bids
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

$item_id = (int)($_GET['id'] ?? 0);
$limit = (int)($_GET['limit'] ?? 20);

if (!$item_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Item ID required']));
}

// Validate limit (clamp both ends — a negative limit becomes "LIMIT -1", which
// errors and silently returns an empty feed flashing "No bids yet").
$limit = max(1, min($limit, 100));

// Get recent bids
$bids = getRecentBids($item_id, $limit);

// Format response
$formatted_bids = [];
foreach ($bids as $bid) {
    $formatted_bids[] = [
        'id' => (int)$bid['id'],
        'bid_amount' => (float)$bid['bid_amount'],
        'bidder_name' => $bid['full_name'] ?: 'Anonymous',
        'created_at' => $bid['created_at'],
        'time_ago' => getTimeAgo($bid['created_at'])
    ];
}

// Viewer-relative status so the item page can show a live winning/outbid
// indicator (green when you're leading, red the moment you're surpassed).
$item_state = getItemState($item_id);
$viewer = getCurrentUser();
$viewer_id = $viewer ? (int)$viewer['id'] : 0;
$viewer_has_bid = false;
$viewer_is_winning = false;
if ($viewer_id && $item_state) {
    $viewer_has_bid = (int)dbGetValue(
        "SELECT COUNT(*) FROM bids WHERE item_id = ? AND user_id = ?",
        [$item_id, $viewer_id]
    ) > 0;
    $viewer_is_winning = $viewer_has_bid
        && (int)($item_state['current_high_bidder_id'] ?? 0) === $viewer_id;
}

// SERVER-authoritative minimum for the next bid. The client must use this
// (not its own arithmetic) for the Quick Bid amount so the button can never
// diverge from what the server will actually accept.
$feed_has_bids = $item_state
    && (float)$item_state['current_high_bid'] > 0
    && !empty($item_state['current_high_bidder_id']);
$next_minimum = $item_state
    ? ($feed_has_bids
        ? round(calculateNextBid((float)$item_state['current_high_bid'], (float)$item_state['min_increment']), 2)
        : (float)$item_state['starting_bid'])
    : 0.0;

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'bids' => $formatted_bids,
    'count' => count($formatted_bids),
    'current_high_bid' => (float)($item_state['current_high_bid'] ?? 0),
    'has_bids' => $feed_has_bids,
    'next_minimum' => $next_minimum,
    'is_closed' => (int)($item_state['is_closed'] ?? 0),
    'viewer_has_bid' => $viewer_has_bid,
    'viewer_is_winning' => $viewer_is_winning
]);

/**
 * Format timestamp as "time ago" string
 */
function getTimeAgo($timestamp) {
    $time_now = time();
    $time_then = strtotime($timestamp);
    $diff = $time_now - $time_then;

    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } else {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
}

?>
