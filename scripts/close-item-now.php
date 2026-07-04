<?php
// ============================================================
// CLOSE ONE ITEM NOW  (testing / on-demand closeout helper)
//
// Closes a SINGLE auction item immediately and runs the exact same
// winner-processing as the automatic closer — it creates the winner's
// pending payment request and sends the "you won" notification — then
// marks the item closed. This is the safe way to open checkout for a
// tester who is currently the high bidder, without touching any other
// item (unlike "Close Expired Auctions", which closes everything whose
// timer has passed).
//
// Web (recommended): sign in to the admin console first, then visit
//     https://silentbidpro.peoplestar.com/scripts/close-item-now.php?id=5
//   The admin session cookie authorizes the request.
//
// CLI (on the server):
//     php scripts/close-item-now.php 5
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db-helpers.php';
require_once __DIR__ . '/../includes/auction-engine.php';

$is_cli = (PHP_SAPI === 'cli');

// ---- Authorization + input ----
if ($is_cli) {
    $item_id = (int)($argv[1] ?? 0);
} else {
    require_once __DIR__ . '/../includes/auth.php';
    requireAdminAuth(); // dies with 401 JSON if the caller is not an authenticated admin
    header('Content-Type: text/plain; charset=utf-8');
    $item_id = (int)($_GET['id'] ?? 0);
}

/**
 * Print a line and, on the web, set an HTTP status. Then optionally stop.
 */
function respond($message, $status = 200, $stop = false) {
    if (PHP_SAPI !== 'cli') {
        http_response_code($status);
    }
    echo $message . PHP_EOL;
    if ($stop) {
        exit($status >= 400 && PHP_SAPI === 'cli' ? 1 : 0);
    }
}

if ($item_id <= 0) {
    respond('Usage: provide an item id (?id=<n> in the browser, or `php close-item-now.php <n>` on the server).', 400, true);
}

// ---- Load the item ----
$item = dbGetRow(
    "SELECT id, item_number, title, current_high_bid, current_high_bidder_id, is_closed
     FROM items WHERE id = ?",
    [$item_id]
);

if (!$item) {
    respond("Item #{$item_id} was not found.", 404, true);
}

$label = 'Item ' . ($item['item_number'] ? '#' . $item['item_number'] . ' ' : '') . '"' . $item['title'] . '"';

if ((int)$item['is_closed'] === 1) {
    respond("{$label} is already closed. Nothing to do — the winner can check out from My Bids.", 200, true);
}

// ---- Process the winner (creates payment request + sends notification) ----
$winner_note = 'No bids were placed, so the item closes with no winner.';

if (!empty($item['current_high_bidder_id'])) {
    $winner = dbGetRow(
        "SELECT id, phone_number, full_name FROM users WHERE id = ?",
        [(int)$item['current_high_bidder_id']]
    );

    if (!$winner) {
        // High bidder record is gone — unrecoverable. Close anyway so it isn't stuck.
        $winner_note = "High bidder #{$item['current_high_bidder_id']} no longer exists; closing without winner processing.";
        error_log("[close-item-now] {$label}: {$winner_note}");
    } else {
        $ok = processWinner(
            (int)$item['id'],
            (int)$winner['id'],
            (float)$item['current_high_bid'],
            $item['title'],
            $winner['phone_number']
        );

        if (!$ok) {
            // Leave the item OPEN so it can be retried, exactly like the auto-closer.
            respond(
                "Could not prepare the winner's payment for {$label}. The item was left OPEN so it can be retried. Check the logs.",
                500,
                true
            );
        }

        $winner_note = "Winner: {$winner['full_name']} — \$" . number_format((float)$item['current_high_bid'], 2)
            . ". Payment request created and win notification sent.";
    }
}

// ---- Mark closed. Also pin the end time to now so the public page stops counting down. ----
dbUpdate("UPDATE items SET is_closed = 1, auction_end_time = NOW() WHERE id = ?", [(int)$item['id']]);

// ---- Audit trail ----
dbInsert(
    "INSERT INTO audit_log (event_type, item_id, description, created_at) VALUES (?, ?, ?, NOW())",
    ['AUCTION_CLOSED', (int)$item['id'], 'Auction closed manually via close-item-now']
);

respond("✓ Closed {$label}.\n{$winner_note}\nThe winner can now open My Bids and complete checkout.");
