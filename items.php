<?php
// ============================================================
// SILENT BID BUDDY — Items Listing
// Browse all active auction items
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db-helpers.php';
require_once __DIR__ . '/includes/events.php';
require_once __DIR__ . '/includes/favorites.php';
require_once __DIR__ . '/includes/public-nav.php';

// Check authentication
$user = getCurrentUser();

$event = getActiveEvent();
$categories = $event ? getEventCategories((int)$event['id']) : [];
$is_authenticated = $user !== false;
$search = trim($_GET['q'] ?? '');
$category_id = (int)($_GET['category'] ?? 0);
$has_event_columns = dbColumnExists('items', 'event_id') && dbColumnExists('items', 'close_time_override');
$has_category_columns = dbColumnExists('items', 'category_id') && dbTableExists('categories');
$has_favorites = favoritesAvailable();

$close_expr = $has_event_columns ? "COALESCE(i.close_time_override, i.auction_end_time)" : "i.auction_end_time";
$select_fields = "i.id, i.item_number, i.title, i.description, i.image_url, i.fair_market_value,
        i.starting_bid, i.current_high_bid, i.auction_end_time, i.is_closed,
        (SELECT COUNT(*) FROM bids b WHERE b.item_id = i.id) AS bid_count,
        TIMESTAMPDIFF(SECOND, NOW(), {$close_expr}) as time_remaining";

if ($has_event_columns) {
    $select_fields .= ", i.close_time_override, i.event_id";
}

if ($has_category_columns) {
    $select_fields .= ", i.category_id, c.name AS category_name";
}

if ($has_favorites && $is_authenticated) {
    $select_fields .= ", CASE WHEN f.id IS NULL THEN 0 ELSE 1 END AS is_favorited";
}

$sql = "SELECT {$select_fields}
        FROM items i";
$params = [];

if ($has_category_columns) {
    $sql .= " LEFT JOIN categories c ON c.id = i.category_id";
}

if ($has_favorites && $is_authenticated) {
    $sql .= " LEFT JOIN favorites f ON f.item_id = i.id AND f.user_id = ?";
    $params[] = (int)$user['id'];
}

$where = ["i.is_closed = 0", "{$close_expr} > NOW()"];

if ($has_event_columns && $event) {
    $where[] = "i.event_id = ?";
    $params[] = (int)$event['id'];
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

$sql .= " WHERE " . implode(" AND ", $where) . " ORDER BY {$close_expr} ASC";
$items = dbGetAll($sql, $params);

$event_name = $event['name'] ?? 'Active Auction';
$organization_name = $event['organization_name'] ?? APP_NAME;
$page_title = $event_name . ' - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/mobile.css">
</head>
<body class="items-list-page">
    <?php renderPublicHeader(['user' => $user]); ?>

    <div class="container items-container">
        <!-- Event Header -->
        <section class="event-hero">
            <p class="event-org"><?php echo htmlspecialchars($organization_name); ?></p>
            <h2><?php echo htmlspecialchars($event_name); ?></h2>
            <?php if (!empty($event['auction_end_time'])): ?>
                <p class="event-close">Main auction closes <?php echo date('M j, Y g:i A', strtotime($event['auction_end_time'])); ?></p>
            <?php endif; ?>
            <div class="event-actions">
                <?php if ($is_authenticated): ?>
                    <a href="my-bids.php" class="btn btn-secondary">My Bids</a>
                <?php else: ?>
                    <a href="index.php?return=<?php echo urlencode('items.php'); ?>" class="btn btn-primary">Sign In to Bid</a>
                <?php endif; ?>
            </div>
        </section>

        <!-- Browse Controls -->
        <section class="browse-controls">
            <form class="browse-search" method="get" action="items.php">
                <?php if ($category_id > 0): ?>
                    <input type="hidden" name="category" value="<?php echo (int)$category_id; ?>" />
                <?php endif; ?>
                <input
                    type="search"
                    name="q"
                    class="form-input"
                    placeholder="Search auction items"
                    value="<?php echo htmlspecialchars($search); ?>"
                />
                <button type="submit" class="btn btn-primary">Search</button>
            </form>

            <?php if (!empty($categories)): ?>
                <div class="category-filter">
                    <a class="category-chip <?php echo $category_id === 0 ? 'active' : ''; ?>" href="items.php<?php echo $search ? '?q=' . urlencode($search) : ''; ?>">All</a>
                    <?php foreach ($categories as $category): ?>
                        <?php
                            $query = ['category' => (int)$category['id']];
                            if ($search !== '') $query['q'] = $search;
                        ?>
                        <a
                            class="category-chip <?php echo $category_id === (int)$category['id'] ? 'active' : ''; ?>"
                            href="items.php?<?php echo http_build_query($query); ?>"
                        >
                            <?php echo htmlspecialchars($category['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Items Grid -->
        <section class="items-section">
            <h2>Browse Items</h2>

            <?php if (empty($items)): ?>
                <div class="no-items-message">
                    <p>No active items match your filters.</p>
                    <p>Try another search or category.</p>
                </div>
            <?php else: ?>
                <div class="items-grid">
                    <?php foreach ($items as $item): ?>
                        <div class="item-card">
                            <!-- Item Image -->
                            <div class="item-card-image">
                                <?php if ($item['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>"
                                         alt="<?php echo htmlspecialchars($item['title']); ?>"
                                    />
                                <?php else: ?>
                                    <div class="image-placeholder-card">
                                        <span>Image coming soon</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Item Info -->
                            <div class="item-card-info">
                                <h3 class="item-card-title">
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </h3>

                                <?php if (!empty($item['category_name'])): ?>
                                    <p class="item-card-category"><?php echo htmlspecialchars($item['category_name']); ?></p>
                                <?php endif; ?>

                                <p class="item-card-description">
                                    <?php echo htmlspecialchars(substr($item['description'], 0, 100)); ?>
                                    <?php if (strlen($item['description']) > 100): ?>...<?php endif; ?>
                                </p>

                                <!-- Bid Info -->
                                <div class="item-card-bid-info">
                                    <div class="bid-stat">
                                        <?php $card_has_bids = (int)($item['bid_count'] ?? 0) > 0 && (float)$item['current_high_bid'] > 0; ?>
                                        <span class="label"><?php echo $card_has_bids ? 'Current Bid:' : 'Opening Bid:'; ?></span>
                                        <span class="value">
                                            $<?php echo number_format($card_has_bids ? (float)$item['current_high_bid'] : (float)$item['starting_bid'], 2); ?>
                                        </span>
                                    </div>

                                    <div class="bid-stat">
                                        <span class="label">Starting:</span>
                                        <span class="value">$<?php echo number_format($item['starting_bid'], 2); ?></span>
                                    </div>
                                </div>

                                <!-- Time Remaining -->
                                <div class="item-card-timer">
                                    <?php
                                        $hours = floor($item['time_remaining'] / 3600);
                                        $mins = floor(($item['time_remaining'] % 3600) / 60);
                                        $secs = $item['time_remaining'] % 60;

                                        if ($hours > 0) {
                                            $time_text = $hours . 'h ' . $mins . 'm';
                                        } elseif ($mins > 0) {
                                            $time_text = $mins . 'm ' . $secs . 's';
                                        } else {
                                            $time_text = $secs . 's';
                                        }
                                    ?>
                                    <span class="time-label"><?php echo $time_text; ?> left</span>
                                    <?php if (!empty($item['close_time_override'])): ?>
                                        <span class="time-note">Custom close</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Action Button -->
                                <div class="item-card-actions">
                                    <a href="item.php?id=<?php echo (int)$item['id']; ?>"
                                       class="btn btn-primary btn-full-width">
                                        View & Bid
                                    </a>
                                    <?php if ($is_authenticated && $has_favorites): ?>
                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-watch js-watch-item <?php echo !empty($item['is_favorited']) ? 'is-active' : ''; ?>"
                                            data-item-id="<?php echo (int)$item['id']; ?>"
                                            aria-pressed="<?php echo !empty($item['is_favorited']) ? 'true' : 'false'; ?>"
                                        >
                                            <?php echo !empty($item['is_favorited']) ? 'Watching' : 'Watch'; ?>
                                        </button>
                                    <?php elseif (!$is_authenticated): ?>
                                        <a
                                            href="index.php?return=<?php echo urlencode('items.php'); ?>"
                                            class="btn btn-secondary btn-watch"
                                        >
                                            Watch
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Navigation -->
        <section class="navigation-section">
            <a href="index.php" class="btn btn-secondary">← Back</a>
        </section>

        <section id="how-bidding-works" class="how-bidding-works" aria-labelledby="howBiddingWorksTitle">
            <h2 id="howBiddingWorksTitle">How Bidding Works</h2>
            <div class="how-steps">
                <div>
                    <strong>1</strong>
                    <span>Sign in once with your phone number.</span>
                </div>
                <div>
                    <strong>2</strong>
                    <span>Bid the next amount or enter the maximum you are willing to pay.</span>
                </div>
                <div>
                    <strong>3</strong>
                    <span>Watch your items from My Bids and pay if you win.</span>
                </div>
            </div>
        </section>
    </div>

    <script src="js/app.js"></script>
    <script>
        document.querySelectorAll('.js-watch-item').forEach((button) => {
            button.addEventListener('click', async () => {
                const itemId = parseInt(button.dataset.itemId, 10);
                const nextState = button.getAttribute('aria-pressed') !== 'true';

                button.disabled = true;

                try {
                    const response = await SBB.API.post('/api/bidding/toggle-favorite.php', {
                        item_id: itemId,
                        favorite: nextState
                    });

                    if (response.status === 'ok') {
                        button.classList.toggle('is-active', response.is_favorited);
                        button.setAttribute('aria-pressed', response.is_favorited ? 'true' : 'false');
                        button.textContent = response.is_favorited ? 'Watching' : 'Watch';
                    } else {
                        SBB.UI.showNotice(response.message || 'Could not update watchlist', 'error');
                    }
                } catch (err) {
                    SBB.UI.showNotice('Network error. Please try again.', 'error');
                } finally {
                    button.disabled = false;
                }
            });
        });
    </script>
</body>
</html>
