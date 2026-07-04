<?php
// ============================================================
// SILENT BID PRO — Item Detail & Bidding Interface
// Main auction page with real-time updates
// ============================================================

// CRITICAL: Prevent page caching - users must see live bids
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db-helpers.php';
require_once __DIR__ . '/includes/favorites.php';
require_once __DIR__ . '/includes/public-nav.php';
require_once __DIR__ . '/includes/branding-helper.php';

// Get item ID from URL
$item_num = $_GET['id'] ?? 0;
if (!$item_num) {
    header('Location: items.php');
    exit;
}

// Fetch item by number (or ID fallback)
$item = dbGetRow(
    "SELECT id, item_number, event_id, title, description, image_url, fair_market_value,
            starting_bid, min_increment, buy_now_price, current_high_bid,
            current_high_bidder_id, auction_end_time, is_closed,
            (SELECT COUNT(*) FROM bids b WHERE b.item_id = items.id) AS bid_count
     FROM items WHERE item_number = ? OR id = ? LIMIT 1",
    [(int)$item_num, (int)$item_num]
);

// AUCTION ISOLATION: block items that belong to a different auction than the one
// this session is locked to, so a bidder can't reach another auction's items.
if ($item) {
    $pinned_event_id = bidderPinnedEventId();
    if ($pinned_event_id && (int)$item['event_id'] !== $pinned_event_id) {
        renderPublicMessagePage([
            'status' => 403,
            'title' => 'Different Auction',
            'heading' => 'That item is in another auction',
            'message' => 'You can only view and bid on items in your own auction.',
            'actions' => [
                ['href' => 'items.php', 'label' => 'Back to Your Auction', 'class' => 'btn-primary']
            ]
        ]);
    }
}

if (!$item) {
    renderPublicMessagePage([
        'status' => 404,
        'title' => 'Item Not Found',
        'heading' => 'We could not find that auction item',
        'message' => 'The item may have been removed, closed, or the link may be incorrect.',
        'actions' => [
            ['href' => 'items.php', 'label' => 'Browse Items', 'class' => 'btn-primary']
        ]
    ]);
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
$has_bids = (int)($item['bid_count'] ?? 0) > 0 && (float)$item['current_high_bid'] > 0 && !empty($item['current_high_bidder_id']);
$is_user_winning = $has_bids && $is_authenticated && (int)$item['current_high_bidder_id'] === (int)$user['id'];
$display_bid_amount = $has_bids ? (float)$item['current_high_bid'] : (float)$item['starting_bid'];
$next_bid_amount = $has_bids
    ? (float)$item['current_high_bid'] + (float)$item['min_increment']
    : (float)$item['starting_bid'];
$time_remaining = strtotime($item['auction_end_time']) - time();
$is_auction_open = !$item['is_closed'] && $time_remaining > 0;
$has_favorites = favoritesAvailable();
$is_favorited = $is_authenticated && $has_favorites && isItemFavorited((int)$user['id'], (int)$item['id']);

// Viewer-relative bid state drives the live green (winning) / red (outbid)
// indicator. 'neutral' = you haven't bid on this item.
$user_has_bid = $is_authenticated && (int)dbGetValue(
    "SELECT COUNT(*) FROM bids WHERE item_id = ? AND user_id = ?",
    [(int)$item['id'], (int)$user['id']]
) > 0;
$bid_state = $user_has_bid ? ($is_user_winning ? 'winning' : 'outbid') : 'neutral';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php renderPageMeta([
        'title' => htmlspecialchars_decode($page_title),
        'description' => 'Bid on ' . $item['title'] . ' and support this fundraising auction through Silent Bid Pro.',
        'type' => 'product'
    ]); ?>
</head>
<body class="item-page" data-vapid-public-key="<?php echo htmlspecialchars(VAPID_PUBLIC_KEY); ?>">
    <?php renderPublicHeader(['back_href' => 'items.php', 'back_label' => '← Items', 'user' => $user]); ?>

    <div class="container item-container">
        <!-- Hero Image Section -->
        <div class="hero-section">
            <?php if ($item['image_url']): ?>
                <?php
                    $imageUrl = $item['image_url'];
                ?>
                <img src="<?php echo htmlspecialchars($imageUrl); ?>"
                     alt="<?php echo htmlspecialchars($item['title']); ?>"
                     class="item-image"
                     onerror="this.onerror=null;this.src='images/items/placeholder.svg';"
                />
            <?php else: ?>
                <div class="image-placeholder">
                    <span>Image coming soon</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Item Info -->
        <section class="item-info">
            <div class="item-title-row">
                <span class="lot-pill">Lot <?php echo (int)$item['item_number']; ?></span>
                <?php if ($is_authenticated && $has_favorites): ?>
                    <button
                        type="button"
                        class="btn btn-secondary btn-watch js-watch-item <?php echo $is_favorited ? 'is-active' : ''; ?>"
                        data-item-id="<?php echo (int)$item['id']; ?>"
                        aria-pressed="<?php echo $is_favorited ? 'true' : 'false'; ?>"
                    >
                        <?php echo $is_favorited ? 'Watching' : 'Watch'; ?>
                    </button>
                <?php endif; ?>
            </div>
            <h1 class="item-title"><?php echo htmlspecialchars($item['title']); ?></h1>
            <p class="item-description">
                <?php echo htmlspecialchars($item['description']); ?>
            </p>
            <?php if ($item['fair_market_value']): ?>
                <div class="fair-market-value-detail">
                    <p class="fair-market-value">
                        💰 <strong>Fair Market Value:</strong> <span class="value">$<?php echo number_format($item['fair_market_value'], 2); ?></span>
                    </p>
                    <p class="fair-market-value-note">
                        This is the estimated retail value of the item. Your bid amount can be higher or lower.
                    </p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Current Bid Block (Highlighted) -->
        <section class="bid-block highlight" data-bid-state="<?php echo $bid_state; ?>">
            <div class="bid-header"><?php echo $has_bids ? 'Current High Bid' : 'Opening Bid'; ?></div>
            <div class="current-bid-amount">
                $<?php echo number_format($display_bid_amount, 2); ?>
            </div>
            <div class="next-minimum-bid">
                <?php echo $has_bids ? 'Next minimum' : 'First bid'; ?>: $<?php echo number_format($next_bid_amount, 2); ?>
            </div>
            <div class="bidder-status">
                <?php if (!$has_bids): ?>
                    <span class="bidder-name">No bids yet. Be the first.</span>
                <?php elseif ($is_user_winning): ?>
                    <span class="badge badge-winning">You're Winning! 🏆</span>
                <?php else: ?>
                    <span class="bidder-name">Another bidder is currently leading</span>
                <?php endif; ?>
            </div>
            <?php if ($bid_state === 'winning'): ?>
                <div class="bid-status-indicator">🏆 You're winning</div>
            <?php elseif ($bid_state === 'outbid'): ?>
                <div class="bid-status-indicator">🔴 You've been outbid — bid again to retake the lead</div>
            <?php endif; ?>
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
                        <a href="bid.php?return=<?php echo urlencode('item.php?id=' . (int)$item['item_number']); ?>" class="btn btn-primary btn-large">Sign In to Bid</a>
                    </p>
                <?php else: ?>
                    <!-- Quick Bid Button -->
                    <button id="quickBidBtn" class="btn btn-primary btn-large btn-quick-bid">
                        <span class="quick-bid-label"><?php echo $has_bids ? 'Quick Bid' : 'Place First Bid'; ?></span>
                        <span class="quick-bid-amount">$<?php echo number_format($next_bid_amount, 2); ?></span>
                    </button>

                    <!-- Custom/Max Bid Section -->
                    <div class="custom-bid-section">
                        <button class="toggle-custom-bid">+ Custom or Max Bid</button>
                        <form id="customBidForm" class="custom-bid-form" style="display: none;">
                            <div class="form-group">
                                <label for="customAmount">Bid Amount</label>
                                <input type="number" id="customAmount" step="0.01" min="0" required />
                            </div>
                            <div class="form-group">
                                <label for="maxAmount">Maximum you'll pay (optional)</label>
                                <input type="number" id="maxAmount" step="0.01" min="0" />
                            </div>
                            <button type="submit" class="btn btn-primary">Place Custom Bid</button>
                        </form>
                    </div>

                    <?php if (!empty($item['buy_now_price']) && (float)$item['buy_now_price'] > 0): ?>
                        <!-- Buy It Now -->
                        <div class="buy-now-section">
                            <button id="buyNowBtn" class="btn btn-secondary btn-large btn-buy-now"
                                    data-buy-now-price="<?php echo (float)$item['buy_now_price']; ?>">
                                Buy It Now for $<?php echo number_format((float)$item['buy_now_price'], 2); ?>
                            </button>
                            <p class="buy-now-hint">Skip the bidding — win this item instantly and close the auction.</p>
                        </div>
                    <?php endif; ?>

                    <div id="bidError" class="error-message" style="display: none;"></div>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <section class="bidding-section">
                <div class="auction-closed-message">
                    <p>⏱️ This auction is closed.</p>
                    <?php if ($is_user_winning): ?>
                        <p>You won! <a href="checkout.php?item_id=<?php echo $item['id']; ?>" class="btn btn-primary">Complete Payment</a></p>
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
            <a href="items.php" class="btn btn-secondary">View All Items</a>
            <?php if ($is_authenticated): ?>
                <a href="my-bids.php" class="btn btn-secondary">My Bids</a>
            <?php endif; ?>
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

    <script src="js/push-notifications.js"></script>
    <script src="js/app.js"></script>
    <script>
        // Pass data to JavaScript
        window.SBB = window.SBB || {};
        window.SBB.itemId = <?php echo (int)$item['id']; ?>;
        window.SBB.isAuthenticated = <?php echo $is_authenticated ? 'true' : 'false'; ?>;
        window.SBB.auctionEndTime = '<?php echo $item['auction_end_time']; ?>';
        window.SBB.currentHighBid = <?php echo (float)$item['current_high_bid']; ?>;
        window.SBB.hasBids = <?php echo $has_bids ? 'true' : 'false'; ?>;
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
    <script src="js/bidding.js"></script>
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

        // CRITICAL: Initialize the bidding system when page loads
        if (window.SBB && window.SBB.Bidding) {
            window.SBB.Bidding.init();
            console.log('[INIT] Bidding system started for item ID:', window.SBB.itemId);
        } else {
            console.error('[ERROR] Bidding module not loaded!');
        }
    </script>
</body>
