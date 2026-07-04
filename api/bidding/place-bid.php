<?php
// ============================================================
// API ENDPOINT: Place Bid
// POST /api/bidding/place-bid.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/events.php';
require_once __DIR__ . '/../../includes/bidding.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/event-notifier.php';

header('Content-Type: application/json');

// CRITICAL: Prevent all caching - bids must sync in real-time
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

// Require authentication
try {
    $user = requireAuth();
} catch (Exception $e) {
    error_log('[BID ERROR] Auth failed: ' . $e->getMessage());
    throw $e;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid JSON']));
}

$item_id = $input['item_id'] ?? 0;
$bid_amount = (float)($input['bid_amount'] ?? 0);
$max_bid_amount = !empty($input['max_bid_amount']) ? (float)$input['max_bid_amount'] : null;

if (!$item_id || $bid_amount <= 0) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid item or bid amount']));
}

// AUCTION ISOLATION: a bidder may only bid within the auction their session is
// locked to. Blocks crossing into another event via a direct item id / API call.
$pinned_event_id = bidderPinnedEventId();
if ($pinned_event_id) {
    $item_event_id = (int)dbGetValue("SELECT event_id FROM items WHERE id = ?", [(int)$item_id]);
    if ($item_event_id && $item_event_id !== $pinned_event_id) {
        error_log('[BID] ⛔ Cross-auction bid blocked: user ' . $user['id'] . ' item ' . $item_id . ' (event ' . $item_event_id . ') vs pinned event ' . $pinned_event_id);
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'This item is not part of your auction.']));
    }
}

// Log attempt
error_log('[BID] User ' . $user['id'] . ' attempting bid of $' . $bid_amount . ' on item ' . $item_id);

// Place bid with transaction locking to prevent race conditions
try {
    $result = placeBidWithLocking($item_id, $user['id'], $bid_amount, $max_bid_amount);
} catch (Exception $e) {
    error_log('[BID] ❌ Transaction failed: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Failed to process bid']));
}

// Log result
error_log('[BID] Result: ' . json_encode($result));

if ($result['status'] !== 'success') {
    error_log('[BID] ❌ FAILED - ' . $result['message']);
    http_response_code(400);
    die(json_encode($result));
}

error_log('[BID] ✓ SUCCESS - Bid ' . $result['bid_id'] . ' placed for $' . $bid_amount);

// Buy It Now: the purchase is committed, but a buy-now win closes the item
// WITHOUT going through the auction closer, so its winner payment request +
// checkout link must be created here (after commit, off the row lock). Without
// this, a buy-now buyer got no transaction record and was never sent a way to pay.
if (!empty($result['buy_now'])) {
    try {
        require_once __DIR__ . '/../../includes/auction-engine.php';
        $bn_item = getItemState($item_id);
        processWinner(
            (int)$item_id,
            (int)$user['id'],
            (float)$result['new_high_bid'],
            $bn_item['title'] ?? 'Item',
            $user['phone_number'] ?? ''
        );
    } catch (\Throwable $e) {
        error_log('[BID] Buy-now winner processing failed (purchase still stands): ' . $e->getMessage());
    }
}

// Send notifications to previous bidder if applicable.
// The bid is ALREADY committed at this point, so a notification failure must
// never turn a successful bid into an error response. Isolate it defensively.
if ($result['previous_high_bidder_id']) {
    try {
        $item = getItemState($item_id);

        // Unified notification dispatch (push + SMS)
        notifyBidPlaced(
            $item_id,
            $user['id'],
            $result['previous_high_bidder_id'],
            $item['title'],
            $bid_amount
        );
    } catch (\Throwable $e) {
        // Log and swallow — the bid stands regardless of notification outcome.
        error_log('[BID] Notification dispatch failed (bid still succeeded): ' . $e->getMessage());
    }
}

http_response_code(200);
echo json_encode($result);

?>
