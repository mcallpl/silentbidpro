<?php
// ============================================================
// API ENDPOINT: Checkout Quote (money breakdown preview)
// POST /api/checkout/quote.php  { item_id, tip_cents?, cover_processing? }
//
// Returns the full "100% to charity" money breakdown for the winner, computed by
// computeWinCheckout(). Read-only / no charge — drives the live checkout UI so
// the bidder sees exactly how their payment splits before paying.
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/money.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

$user = requireAuth();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$item_id = (int)($input['item_id'] ?? 0);
$tip_cents = max(0, (int)($input['tip_cents'] ?? 0));
$cover = array_key_exists('cover_processing', $input) ? (bool)$input['cover_processing'] : true;

if (!$item_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Item ID required']));
}

// Item + its event's org premium config, in one query.
$row = dbGetRow(
    "SELECT i.id, i.title, i.current_high_bid, i.current_high_bidder_id, i.is_closed,
            i.fair_market_value,
            e.id AS event_id, o.id AS org_id, o.name AS organization_name,
            COALESCE(o.premium_mode, 'bidder_pays') AS premium_mode,
            COALESCE(o.premium_rate_bps, 0) AS premium_rate_bps
     FROM items i
     LEFT JOIN events e        ON e.id = i.event_id
     LEFT JOIN organizations o ON o.id = e.organization_id
     WHERE i.id = ?",
    [$item_id]
);

if (!$row) {
    http_response_code(404);
    die(json_encode(['status' => 'error', 'message' => 'Item not found']));
}
if (empty($row['is_closed'])) {
    http_response_code(409);
    die(json_encode(['status' => 'error', 'message' => 'This auction has not closed yet. Payment opens once bidding ends.']));
}
if ($row['current_high_bidder_id'] != $user['id']) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'You are not the winner of this item']));
}

$bid_cents = dollarsToCents($row['current_high_bid']);
$fmv_cents = $row['fair_market_value'] !== null ? dollarsToCents($row['fair_market_value']) : null;

$b = computeWinCheckout([
    'bid_cents'        => $bid_cents,
    'premium_rate_bps' => (int)$row['premium_rate_bps'],
    'premium_mode'     => $row['premium_mode'],
    'tip_cents'        => $tip_cents,
    'cover_processing' => $cover,
    'fmv_cents'        => $fmv_cents,
]);

// GoFundMe-style tip suggestions: % of (bid + premium), rounded to whole dollars.
$tip_base = $b['bid_cents'] + $b['premium_cents'];
$suggested_tips = [];
foreach ([0, 3, 5, 8] as $pct) {
    $suggested_tips[] = [
        'percent' => $pct,
        'cents'   => (int)round($tip_base * $pct / 100 / 100) * 100, // whole-dollar
    ];
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'item' => [
        'id' => (int)$row['id'],
        'title' => $row['title'],
    ],
    'organization_name' => $row['organization_name'],
    'premium_mode' => $b['premium_mode'],
    'breakdown' => [
        'bid_cents'              => $b['bid_cents'],
        'premium_cents'          => $b['premium_cents'],
        'premium_rate_bps'       => $b['premium_rate_bps'],
        'tip_cents'              => $b['tip_cents'],
        'processing_cover_cents' => $b['processing_cover_cents'],
        'total_cents'            => $b['total_cents'],
        'transfer_amount_cents'  => $b['transfer_amount_cents'], // to charity, == bid
    ],
    'display' => [
        'bid'        => centsToDisplay($b['bid_cents']),
        'premium'    => centsToDisplay($b['premium_cents']),
        'tip'        => centsToDisplay($b['tip_cents']),
        'cover'      => centsToDisplay($b['processing_cover_cents']),
        'total'      => centsToDisplay($b['total_cents']),
        'to_charity' => centsToDisplay($b['transfer_amount_cents']),
        'premium_rate' => number_format($b['premium_rate_bps'] / 100, 2) . '%',
    ],
    'guarantee' => 'Your winning bid of ' . centsToDisplay($b['bid_cents']) . ' goes 100% to '
        . ($row['organization_name'] ?: 'the charity') . '.',
    'suggested_tips' => $suggested_tips,
]);
