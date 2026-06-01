<?php
// ============================================================
// SILENT BID BUDDY — Items Listing
// Browse all active auction items
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db-helpers.php';

// Check authentication
$user = getCurrentUser();

// Get all active items
$items = dbGetAll(
    "SELECT id, item_number, title, description, image_url, fair_market_value,
            starting_bid, current_high_bid, auction_end_time, is_closed,
            TIMESTAMPDIFF(SECOND, NOW(), auction_end_time) as time_remaining
     FROM items
     WHERE is_closed = 0 AND auction_end_time > NOW()
     ORDER BY auction_end_time ASC"
);

$page_title = 'All Items - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="/silentbidbuddy/css/main.css">
    <link rel="stylesheet" href="/silentbidbuddy/css/mobile.css">
</head>
<body class="items-list-page">
    <header class="app-header">
        <h1><?php echo APP_NAME; ?></h1>
        <button class="btn-menu">≡</button>
    </header>

    <div class="container items-container">
        <!-- Items Grid -->
        <section class="items-section">
            <h2>Active Auction Items</h2>

            <?php if (empty($items)): ?>
                <div class="no-items-message">
                    <p>🔔 No active items at this time.</p>
                    <p>Check back soon!</p>
                </div>
            <?php else: ?>
                <div class="items-grid">
                    <?php foreach ($items as $item): ?>
                        <div class="item-card">
                            <!-- Item Image -->
                            <div class="item-card-image">
                                <?php if ($item['image_url']): ?>
                                    <?php
                                        $imageUrl = $item['image_url'];
                                        // Fix image URLs that are missing the /silentbidbuddy/ prefix
                                        if (strpos($imageUrl, '/silentbidbuddy/') === false && strpos($imageUrl, 'data:') !== 0 && strpos($imageUrl, 'http') !== 0) {
                                            if (strpos($imageUrl, '/') === 0) {
                                                $imageUrl = '/silentbidbuddy' . $imageUrl;
                                            }
                                        }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($imageUrl); ?>"
                                         alt="<?php echo htmlspecialchars($item['title']); ?>"
                                    />
                                <?php else: ?>
                                    <div class="image-placeholder-card">
                                        <span>No Image</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Item Info -->
                            <div class="item-card-info">
                                <h3 class="item-card-title">
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </h3>

                                <p class="item-card-description">
                                    <?php echo htmlspecialchars(substr($item['description'], 0, 100)); ?>
                                    <?php if (strlen($item['description']) > 100): ?>...<?php endif; ?>
                                </p>

                                <!-- Bid Info -->
                                <div class="item-card-bid-info">
                                    <div class="bid-stat">
                                        <span class="label">Current Bid:</span>
                                        <span class="value">
                                            $<?php echo number_format(max($item['current_high_bid'], $item['starting_bid']), 2); ?>
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
                                    <span class="time-label">⏱️ <?php echo $time_text; ?></span>
                                </div>

                                <!-- Action Button -->
                                <a href="/silentbidbuddy/item.php?id=<?php echo (int)$item['item_number']; ?>"
                                   class="btn btn-primary btn-full-width">
                                    View & Bid
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Navigation -->
        <section class="navigation-section">
            <a href="/silentbidbuddy/index.php" class="btn btn-secondary">← Back</a>
        </section>
    </div>

    <script src="/silentbidbuddy/js/app.js"></script>
</body>
</html>
