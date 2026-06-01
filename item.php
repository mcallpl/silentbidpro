<?php
// ============================================================
// SILENT BID BUDDY — Item Detail & Bidding Interface
// Main auction page with real-time updates
// ============================================================

// CRITICAL: Prevent page caching - users must see live bids
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db-helpers.php';

// Get item ID from URL
$item_num = $_GET['id'] ?? 0;
if (!$item_num) {
    header('Location: /silentbidbuddy/items.php');
    exit;
}

// Fetch item by number (or ID fallback)
$item = dbGetRow(
    "SELECT id, item_number, title, description, image_url, fair_market_value,
            starting_bid, min_increment, buy_now_price, current_high_bid,
            auction_end_time, is_closed
     FROM items WHERE item_number = ? OR id = ? LIMIT 1",
    [(int)$item_num, (int)$item_num]
);

if (!$item) {
    http_response_code(404);
    die("Item not found");
}

$page_title = htmlspecialchars($item['title']) . ' - ' . APP_NAME;
$user = getCurrentUser();
$is_authenticated = $user !== false;

// CRITICAL: If PHP auth failed but user has localStorage token, they may have logged in
// This can happen if cookies aren't persisting properly. We'll let client-side JS handle it.
$has_local_storage_token = false;
if (!$is_authenticated) {
    // Check if there's a token in GET param (fallback from client-side redirect)
    if (!empty($_GET['auth_token'])) {
        $token = validateSessionToken($_GET['auth_token']);
        if ($token) {
            $user = $token;
            $is_authenticated = true;
        }
    }
}
$is_user_winning = $is_authenticated && $item['current_high_bidder_id'] == $user['id'];
$time_remaining = strtotime($item['auction_end_time']) - time();
$is_auction_open = !$item['is_closed'] && $time_remaining > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/silentbidbuddy/css/main.css">
    <link rel="stylesheet" href="/silentbidbuddy/css/mobile.css">
</head>
<body class="item-page">
    <header class="app-header">
        <button class="btn-back" onclick="history.back()">← Back</button>
        <h1><?php echo APP_NAME; ?></h1>
        <button class="btn-menu">≡</button>
    </header>

    <div class="container item-container">
        <!-- Hero Image Section -->
        <div class="hero-section">
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
                     class="item-image"
                />
            <?php else: ?>
                <div class="image-placeholder">
                    <span>No Image Available</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Item Info -->
        <section class="item-info">
            <h1 class="item-title"><?php echo htmlspecialchars($item['title']); ?></h1>
            <p class="item-description">
                <?php echo htmlspecialchars($item['description']); ?>
            </p>
            <?php if ($item['fair_market_value']): ?>
                <p class="fair-market-value">
                    Fair Market Value: <span class="value">$<?php echo number_format($item['fair_market_value'], 2); ?></span>
                </p>
            <?php endif; ?>
        </section>

        <!-- Current Bid Block (Highlighted) -->
        <section class="bid-block highlight">
            <div class="bid-header">Current High Bid</div>
            <div class="current-bid-amount">
                $<?php echo number_format(max($item['current_high_bid'], $item['starting_bid']), 2); ?>
            </div>
            <div class="next-minimum-bid">
                Next minimum: $<?php echo number_format($item['current_high_bid'] > 0 ? $item['current_high_bid'] + $item['min_increment'] : $item['starting_bid'], 2); ?>
            </div>
            <div class="bidder-status">
                <?php if ($is_user_winning): ?>
                    <span class="badge badge-winning">You're Winning! 🏆</span>
                <?php else: ?>
                    <span class="bidder-name">Bid by Someone Else</span>
                <?php endif; ?>
            </div>
        </section>

        <!-- Countdown Timer -->
        <section class="countdown-section" id="countdownSection">
            <div class="countdown-label">Time Remaining</div>
            <div class="countdown-timer" id="countdownTimer">
                <?php
                    if ($is_auction_open) {
                        $mins = floor($time_remaining / 60);
                        $secs = $time_remaining % 60;
                        echo sprintf("%d:%02d", $mins, $secs);
                    } else {
                        echo "Auction Closed";
                    }
                ?>
            </div>
        </section>

        <!-- Bidding Interface -->
        <?php if ($is_auction_open): ?>
            <section class="bidding-section">
                <?php if (!$is_authenticated): ?>
                    <p class="auth-prompt">
                        <a href="/silentbidbuddy/index.php" class="btn btn-primary btn-large">Sign In to Bid</a>
                    </p>
                <?php else: ?>
                    <!-- Quick Bid Button -->
                    <button id="quickBidBtn" class="btn btn-primary btn-large btn-quick-bid">
                        Quick Bid <span class="quick-bid-amount">$<?php echo number_format($item['current_high_bid'] > 0 ? $item['current_high_bid'] + $item['min_increment'] : $item['starting_bid'], 2); ?></span>
                    </button>

                    <!-- Custom/Proxy Bid Section -->
                    <div class="custom-bid-section">
                        <button class="toggle-custom-bid">+ Custom or Proxy Bid</button>
                        <form id="customBidForm" class="custom-bid-form" style="display: none;">
                            <div class="form-group">
                                <label for="customAmount">Bid Amount</label>
                                <input type="number" id="customAmount" step="0.01" min="0" required />
                            </div>
                            <div class="form-group">
                                <label for="maxAmount">Max Bid (Optional - for proxy bidding)</label>
                                <input type="number" id="maxAmount" step="0.01" min="0" />
                            </div>
                            <button type="submit" class="btn btn-primary">Place Custom Bid</button>
                        </form>
                    </div>

                    <div id="bidError" class="error-message" style="display: none;"></div>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <section class="bidding-section">
                <div class="auction-closed-message">
                    <p>⏱️ This auction is closed.</p>
                    <?php if ($is_user_winning): ?>
                        <p>You won! <a href="/silentbidbuddy/checkout.php?item_id=<?php echo $item['id']; ?>" class="btn btn-primary">Complete Payment</a></p>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Bid History Feed -->
        <section class="bid-history">
            <h3>Recent Bids</h3>
            <div id="bidFeed" class="bid-feed">
                <p class="loading">Loading bid history...</p>
            </div>
        </section>

        <!-- Navigation -->
        <section class="navigation-section">
            <a href="/silentbidbuddy/items.php" class="btn btn-secondary">View All Items</a>
        </section>
    </div>

    <!-- Bid Modal (for quick bid confirmation) -->
    <div id="bidModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Confirm Your Bid</h2>
            <p>You're about to bid <strong id="modalBidAmount">$0.00</strong> on <strong><?php echo htmlspecialchars($item['title']); ?></strong></p>
            <div class="modal-buttons">
                <button id="confirmBidBtn" class="btn btn-primary">Confirm Bid</button>
                <button id="cancelBidBtn" class="btn btn-secondary">Cancel</button>
            </div>
        </div>
    </div>

    <script src="/silentbidbuddy/js/app.js"></script>
    <script>
        // Pass data to JavaScript
        window.SBB = window.SBB || {};
        window.SBB.itemId = <?php echo (int)$item['id']; ?>;
        window.SBB.isAuthenticated = <?php echo $is_authenticated ? 'true' : 'false'; ?>;
        window.SBB.auctionEndTime = '<?php echo $item['auction_end_time']; ?>';
        window.SBB.currentHighBid = <?php echo (float)$item['current_high_bid']; ?>;
        window.SBB.minIncrement = <?php echo (float)$item['min_increment']; ?>;
        window.SBB.startingBid = <?php echo (float)$item['starting_bid']; ?>;
        window.SBB.isAuctionOpen = <?php echo $is_auction_open ? 'true' : 'false'; ?>;
        window.SBB.sessionToken = localStorage.getItem('session_token');

        // PERSISTENCE FIX: If PHP says user not authenticated but localStorage has a token,
        // reload with the token as a GET parameter so PHP can validate it.
        // This handles cases where the HTTP-only cookie isn't persisting properly.
        if (!window.SBB.isAuthenticated && window.SBB.sessionToken) {
            const currentUrl = window.location.href;
            if (!currentUrl.includes('auth_token=')) {
                const separator = currentUrl.includes('?') ? '&' : '?';
                window.location.href = currentUrl + separator + 'auth_token=' + encodeURIComponent(window.SBB.sessionToken);
            }
        }
    </script>
    <script src="/silentbidbuddy/js/bidding.js"></script>
    <script>
        // CRITICAL: Initialize the bidding system when page loads
        if (window.SBB && window.SBB.Bidding) {
            window.SBB.Bidding.init();
            console.log('[INIT] Bidding system started for item ID:', window.SBB.itemId);
        } else {
            console.error('[ERROR] Bidding module not loaded!');
        }
    </script>
</body>
