<?php
// ============================================================
// SILENT BID BUDDY — My Bids
// Bidder status dashboard for watched, active, won, and unpaid items
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db-helpers.php';
require_once __DIR__ . '/includes/events.php';
require_once __DIR__ . '/includes/favorites.php';
require_once __DIR__ . '/includes/public-nav.php';
require_once __DIR__ . '/includes/branding-helper.php';

$user = getCurrentUser();
$branding = getBrandingData();
if (!$user) {
    header('Location: bid.php?return=' . urlencode('my-bids.php'));
    exit;
}

$event = getCurrentEvent();
$has_event_columns = dbColumnExists('items', 'event_id') && dbColumnExists('items', 'close_time_override');
$has_favorites = favoritesAvailable();
$close_expr = $has_event_columns ? "COALESCE(i.close_time_override, i.auction_end_time)" : "i.auction_end_time";

$event_filter = '';
$event_params = [];
if ($has_event_columns && $event) {
    $event_filter = ' AND i.event_id = ?';
    $event_params[] = (int)$event['id'];
}

$bid_items = dbGetAll(
    "SELECT
        i.id,
        i.item_number,
        i.title,
        i.image_url,
        i.current_high_bid,
        i.current_high_bidder_id,
        i.starting_bid,
        i.min_increment,
        i.is_closed,
        {$close_expr} AS effective_close_time,
        TIMESTAMPDIFF(SECOND, NOW(), {$close_expr}) AS time_remaining,
        MAX(b.bid_amount) AS my_highest_bid,
        MAX(b.max_bid_amount) AS my_max_bid,
        COUNT(b.id) AS my_bid_count,
        MAX(b.created_at) AS last_bid_at,
        t.status AS transaction_status,
        t.id AS transaction_id
     FROM items i
     JOIN bids b ON b.item_id = i.id AND b.user_id = ?
     LEFT JOIN transactions t ON t.id = (
         SELECT tx.id
         FROM transactions tx
         WHERE tx.item_id = i.id AND tx.user_id = ?
         ORDER BY tx.created_at DESC
         LIMIT 1
     )
     WHERE 1=1 {$event_filter}
     GROUP BY
        i.id,
        i.item_number,
        i.title,
        i.image_url,
        i.current_high_bid,
        i.current_high_bidder_id,
        i.starting_bid,
        i.min_increment,
        i.is_closed,
        effective_close_time,
        time_remaining,
        t.status,
        t.id
     ORDER BY i.is_closed ASC, effective_close_time ASC, last_bid_at DESC",
    array_merge([(int)$user['id'], (int)$user['id']], $event_params)
);

$watched_items = [];
if ($has_favorites) {
    $watched_items = dbGetAll(
        "SELECT
            i.id,
            i.item_number,
            i.title,
            i.image_url,
            i.current_high_bid,
            i.current_high_bidder_id,
            i.starting_bid,
            i.min_increment,
            i.is_closed,
            {$close_expr} AS effective_close_time,
            TIMESTAMPDIFF(SECOND, NOW(), {$close_expr}) AS time_remaining,
            f.created_at AS watched_at
         FROM favorites f
         JOIN items i ON i.id = f.item_id
         WHERE f.user_id = ? {$event_filter}
           AND NOT EXISTS (
               SELECT 1 FROM bids b
               WHERE b.item_id = i.id AND b.user_id = f.user_id
           )
         ORDER BY i.is_closed ASC, effective_close_time ASC, f.created_at DESC",
        array_merge([(int)$user['id']], $event_params)
    );
}

$summary = [
    'winning' => 0,
    'outbid' => 0,
    'won_unpaid' => 0,
    'paid' => 0,
    'watching' => count($watched_items)
];

foreach ($bid_items as $item) {
    $is_winner = (int)$item['current_high_bidder_id'] === (int)$user['id'];
    $is_closed = (int)$item['is_closed'] === 1 || (int)$item['time_remaining'] <= 0;
    $is_paid = ($item['transaction_status'] ?? '') === 'paid';

    if ($is_closed && $is_winner && !$is_paid) {
        $summary['won_unpaid']++;
    } elseif ($is_closed && $is_winner && $is_paid) {
        $summary['paid']++;
    } elseif (!$is_closed && $is_winner) {
        $summary['winning']++;
    } elseif (!$is_closed) {
        $summary['outbid']++;
    }
}

function renderBidItemCard($item, $user_id, $kind = 'bid') {
    $is_winner = (int)($item['current_high_bidder_id'] ?? 0) === (int)$user_id;
    $is_closed = (int)$item['is_closed'] === 1 || (int)($item['time_remaining'] ?? 0) <= 0;
    $is_paid = ($item['transaction_status'] ?? '') === 'paid';
    $current = max((float)$item['current_high_bid'], (float)$item['starting_bid']);
    $next = (float)$item['current_high_bid'] > 0
        ? (float)$item['current_high_bid'] + (float)$item['min_increment']
        : (float)$item['starting_bid'];

    if ($kind === 'watch') {
        $label = 'Watching';
        $class = 'status-watch';
    } elseif ($is_closed && $is_winner && $is_paid) {
        $label = 'Paid';
        $class = 'status-paid';
    } elseif ($is_closed && $is_winner) {
        $label = 'Won - Payment Due';
        $class = 'status-due';
    } elseif (!$is_closed && $is_winner) {
        $label = 'Winning';
        $class = 'status-winning';
    } elseif ($is_closed) {
        $label = 'Closed';
        $class = 'status-closed';
    } else {
        $label = 'Outbid';
        $class = 'status-outbid';
    }
    ?>
    <article class="my-bid-card">
        <div class="my-bid-image">
            <?php if (!empty($item['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" />
            <?php else: ?>
                <span>Image coming soon</span>
            <?php endif; ?>
        </div>
        <div class="my-bid-content">
            <div class="my-bid-topline">
                <span>Lot <?php echo (int)$item['item_number']; ?></span>
                <span class="my-bid-status <?php echo $class; ?>"><?php echo htmlspecialchars($label); ?></span>
            </div>
            <h3><?php echo htmlspecialchars($item['title']); ?></h3>
            <div class="my-bid-stats">
                <span>Current: $<?php echo number_format($current, 2); ?></span>
                <?php if ($kind === 'bid'): ?>
                    <span>Your bid: $<?php echo number_format((float)$item['my_highest_bid'], 2); ?></span>
                <?php else: ?>
                    <span>Next bid: $<?php echo number_format($next, 2); ?></span>
                <?php endif; ?>
            </div>
            <div class="my-bid-actions">
                <a href="item.php?id=<?php echo (int)$item['id']; ?>" class="btn btn-primary">
                    <?php echo ($is_closed && $is_winner && !$is_paid) ? 'Pay or View' : 'View Item'; ?>
                </a>
                <?php if ($is_closed && $is_winner && !$is_paid): ?>
                    <a href="checkout.php?item_id=<?php echo (int)$item['id']; ?>" class="btn btn-secondary">Checkout</a>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
}

$page_title = 'My Bids - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php renderPageMeta([
        'title' => $page_title,
        'description' => 'Track your Silent Bid Buddy bids, watched items, winning status, and checkout steps.'
    ]); ?>
</head>
<body class="items-list-page my-bids-page" data-vapid-public-key="<?php echo htmlspecialchars(VAPID_PUBLIC_KEY); ?>">
    <?php renderPublicHeader(['back_href' => 'items.php', 'back_label' => '← Items', 'user' => $user]); ?>

    <main class="container my-bids-container">
        <?php if ($branding): ?>
            <?php renderEventBanner(['show_logo' => false, 'show_mission' => false]); ?>
        <?php else: ?>
            <section class="event-hero">
                <p class="event-org"><?php echo htmlspecialchars($event['organization_name'] ?? APP_NAME); ?></p>
                <h2>My Bids</h2>
                <p class="event-close">Welcome back, <?php echo htmlspecialchars($user['full_name'] ?: 'friend'); ?>.</p>
            </section>
        <?php endif; ?>

        <div style="padding: 0.5rem 0; text-align: center; color: var(--color-medium); font-size: 0.9rem;">
            Welcome back, <?php echo htmlspecialchars($user['full_name'] ?: 'friend'); ?>
        </div>

        <section class="bid-summary-grid" aria-label="Bid summary">
            <div class="bid-summary-card">
                <strong><?php echo $summary['winning']; ?></strong>
                <span>Winning</span>
            </div>
            <div class="bid-summary-card">
                <strong><?php echo $summary['outbid']; ?></strong>
                <span>Outbid</span>
            </div>
            <div class="bid-summary-card">
                <strong><?php echo $summary['won_unpaid']; ?></strong>
                <span>Payment Due</span>
            </div>
            <div class="bid-summary-card">
                <strong><?php echo $summary['watching']; ?></strong>
                <span>Watching</span>
            </div>
        </section>

        <section class="my-bids-section">
            <div class="section-heading-row">
                <h2>Bid Activity</h2>
                <a href="items.php" class="btn btn-secondary btn-small">Browse More</a>
            </div>

            <?php if (empty($bid_items)): ?>
                <div class="no-items-message">
                    <p>You have not placed any bids yet.</p>
                    <p>Find something you love and make your first bid.</p>
                </div>
            <?php else: ?>
                <div class="my-bids-list">
                    <?php foreach ($bid_items as $item): ?>
                        <?php renderBidItemCard($item, (int)$user['id']); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($has_favorites): ?>
            <section class="my-bids-section">
                <div class="section-heading-row">
                    <h2>Watching</h2>
                </div>

                <?php if (empty($watched_items)): ?>
                    <div class="no-items-message compact">
                        <p>No watched items yet.</p>
                    </div>
                <?php else: ?>
                    <div class="my-bids-list">
                        <?php foreach ($watched_items as $item): ?>
                            <?php renderBidItemCard($item, (int)$user['id'], 'watch'); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
    <script src="js/push-notifications.js"></script>
    <script src="js/app.js"></script>
</body>
</html>
