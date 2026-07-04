<?php
// ============================================================
// START / RESTART AUCTION  (test sessions)
//
// A self-serve control so a testing group can give their auction a
// finite countdown (e.g. 30 or 60 minutes). Starting resets every item
// in the event to a fresh state and sets a new end time; when the timer
// runs out the auction closes automatically (cron) and winners can pay.
//
// Gated behind SBB_TEST_MODE so it never appears on a real production
// server. Scoped to the event the visitor is pinned to (?event=<slug>).
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db-helpers.php';
require_once __DIR__ . '/includes/events.php';
require_once __DIR__ . '/includes/public-nav.php';
require_once __DIR__ . '/includes/branding-helper.php';

// Only available on a test server.
if (!defined('SBB_TEST_MODE') || !SBB_TEST_MODE) {
    renderPublicMessagePage([
        'status' => 404,
        'title' => 'Not available',
        'heading' => 'This tool is not available',
        'message' => 'The auction timer control is only enabled on test servers.'
    ]);
}

$event = getCurrentEvent();
if (!$event) {
    renderPublicMessagePage([
        'status' => 404,
        'title' => 'Start Auction',
        'heading' => 'No auction selected',
        'message' => 'Open your group\'s auction link first, then come back to start the timer.'
    ]);
}
$event_id = (int)$event['id'];
$event_slug = $event['slug'];

// Must be signed in (so a random visitor can't reset an auction).
$user = getCurrentUser();
if (!$user) {
    header('Location: bid.php?return=' . urlencode('start-auction.php?event=' . $event_slug));
    exit;
}

// ---- Handle "start" ----
$started_minutes = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A preset button submits `minutes`; the custom field submits `minutes_custom`.
    // They must NOT share a name — an empty custom field would override the button.
    $minutes = (int)($_POST['minutes'] ?? 0);
    if ($minutes <= 0) {
        $minutes = (int)($_POST['minutes_custom'] ?? 0);
    }
    $minutes = max(0, min(600, $minutes));
    if ($minutes > 0) {
        // Fresh run: clear bids and pending payments, reopen items, set the clock.
        dbDelete("DELETE FROM bids WHERE item_id IN (SELECT id FROM items WHERE event_id = ?)", [$event_id]);
        dbDelete("DELETE FROM transactions WHERE status = 'pending' AND item_id IN (SELECT id FROM items WHERE event_id = ?)", [$event_id]);
        dbUpdate(
            "UPDATE items
                SET is_closed = 0,
                    current_high_bid = starting_bid,
                    current_high_bidder_id = NULL,
                    auction_start_time = NOW(),
                    auction_end_time = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                    close_time_override = NULL
              WHERE event_id = ?",
            [$minutes, $event_id]
        );
        $started_minutes = $minutes;
    }
}

// ---- Current status ----
$stats = dbGetRow(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN is_closed = 0 THEN 1 ELSE 0 END) AS open_items,
            MAX(auction_end_time) AS ends_at
     FROM items WHERE event_id = ?",
    [$event_id]
);
$ends_at = $stats['ends_at'] ?? null;
$open_items = (int)($stats['open_items'] ?? 0);

$page_title = 'Start Auction - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php renderPageMeta(['title' => $page_title, 'description' => 'Start a timed test auction.']); ?>
    <style>
        .timer-card { max-width: 560px; margin: 1.5rem auto; background: #fff; border: 1px solid #e6e2d9;
            border-radius: 16px; padding: 1.75rem; box-shadow: 0 10px 30px rgba(0,0,0,.06); }
        .timer-card h2 { margin: 0 0 .35rem; }
        .timer-sub { color: #5a6072; margin: 0 0 1.25rem; }
        .timer-status { background: #eef6f4; border-radius: 10px; padding: .85rem 1rem; margin-bottom: 1.25rem; font-size: .95rem; }
        .timer-status.live { background: #e6f4ea; color: #1b5e20; }
        .dur-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: .6rem; margin: 0 0 1rem; }
        .dur-grid button { padding: .9rem; font-size: 1.05rem; font-weight: 700; border-radius: 12px;
            border: 2px solid var(--event-brand-primary, #0f726b); background: #fff; color: var(--event-brand-primary, #0f726b); cursor: pointer; }
        .dur-grid button:hover { background: var(--event-brand-primary, #0f726b); color: #fff; }
        .custom-row { display: flex; gap: .5rem; align-items: center; margin-bottom: 1rem; }
        .custom-row input { flex: 1; padding: .7rem; border: 1px solid #ccc; border-radius: 10px; font-size: 1rem; }
        .timer-warn { font-size: .82rem; color: #8a6d1a; background: #fdf6e3; border-radius: 8px; padding: .6rem .8rem; }
        .timer-actions { display: flex; gap: .6rem; margin-top: 1rem; }
    </style>
</head>
<body class="items-list-page" data-vapid-public-key="<?php echo htmlspecialchars(VAPID_PUBLIC_KEY); ?>">
    <?php renderPublicHeader(['title' => APP_NAME, 'back_href' => 'items.php', 'back_label' => '← Items', 'user' => $user]); ?>

    <main class="container">
        <div class="timer-card">
            <h2>Start the auction</h2>
            <p class="timer-sub"><?php echo htmlspecialchars($event['name']); ?></p>

            <?php if ($started_minutes): ?>
                <div class="timer-status live">
                    ✅ Auction started! It runs for <strong><?php echo (int)$started_minutes; ?> minutes</strong>
                    and closes automatically. When it ends, winners can pay from <strong>My Bids</strong>.
                </div>
                <div class="timer-actions">
                    <a class="btn btn-primary" href="items.php?event=<?php echo urlencode($event_slug); ?>">Go bid now →</a>
                </div>
            <?php else: ?>
                <?php if ($open_items > 0 && $ends_at): ?>
                    <div class="timer-status">⏳ An auction is currently running (ends <?php echo date('g:i A', strtotime($ends_at)); ?>). Starting again resets all bids.</div>
                <?php else: ?>
                    <div class="timer-status">No auction is running yet. Pick a length to start.</div>
                <?php endif; ?>

                <p class="timer-sub">When everyone is ready, choose how long bidding stays open:</p>
                <form method="POST" action="start-auction.php?event=<?php echo urlencode($event_slug); ?>">
                    <div class="dur-grid">
                        <button type="submit" name="minutes" value="15">15 min</button>
                        <button type="submit" name="minutes" value="30">30 min</button>
                        <button type="submit" name="minutes" value="60">1 hour</button>
                    </div>
                    <div class="custom-row">
                        <input type="number" name="minutes_custom" min="1" max="600" placeholder="Custom minutes" />
                        <button type="submit" class="btn btn-secondary">Start</button>
                    </div>
                </form>
                <p class="timer-warn">⚠️ Starting resets this auction — it clears all current bids and gives every item a fresh countdown.</p>
            <?php endif; ?>
        </div>
    </main>

    <script src="js/app.js"></script>
</body>
</html>
