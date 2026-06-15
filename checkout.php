<?php
// ============================================================
// SILENT BID BUDDY — Checkout Page
// Stripe Checkout integration
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db-helpers.php';
require_once __DIR__ . '/includes/public-nav.php';

// Require authentication
$user = getCurrentUser();
if (!$user) {
    header('Location: bid.php?return=' . urlencode($_SERVER['REQUEST_URI'] ?? 'checkout.php'));
    exit;
}

// Get item ID
$item_id = $_GET['item_id'] ?? 0;
if (!$item_id) {
    renderPublicMessagePage([
        'status' => 404,
        'title' => 'Checkout',
        'heading' => 'Checkout link needs an item',
        'message' => 'Choose a won item from My Bids to continue payment.',
        'actions' => [
            ['href' => 'my-bids.php', 'label' => 'View My Bids', 'class' => 'btn-primary'],
            ['href' => 'items.php', 'label' => 'Browse Items', 'class' => 'btn-secondary']
        ],
        'user' => $user
    ]);
}

// Fetch item
$item = dbGetRow(
    "SELECT id, title, current_high_bid, current_high_bidder_id FROM items WHERE id = ?",
    [(int)$item_id]
);

if (!$item || $item['current_high_bidder_id'] != $user['id']) {
    renderPublicMessagePage([
        'status' => 403,
        'title' => 'Checkout',
        'heading' => 'This checkout is not available',
        'message' => 'Only the winning bidder can pay for this item. Check My Bids for items ready for payment.',
        'actions' => [
            ['href' => 'my-bids.php', 'label' => 'View My Bids', 'class' => 'btn-primary'],
            ['href' => 'items.php', 'label' => 'Browse Items', 'class' => 'btn-secondary']
        ],
        'user' => $user
    ]);
}

$page_title = 'Checkout - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php renderPageMeta([
        'title' => $page_title,
        'description' => 'Complete your secure Silent Bid Buddy auction payment.'
    ]); ?>
    <script src="https://js.stripe.com/v3/"></script>
</head>
<body class="checkout-page" data-vapid-public-key="<?php echo htmlspecialchars(VAPID_PUBLIC_KEY); ?>">
    <?php renderPublicHeader(['title' => APP_NAME . ' - Payment', 'back_href' => 'item.php?id=' . (int)$item['id'], 'back_label' => '← Item', 'user' => $user]); ?>

    <div class="container checkout-container">
        <section class="checkout-summary">
            <h2>Order Summary</h2>
            <div class="summary-item">
                <span class="item-name"><?php echo htmlspecialchars($item['title']); ?></span>
                <span class="item-amount">$<?php echo number_format($item['current_high_bid'], 2); ?></span>
            </div>
            <div class="summary-total">
                <span class="total-label">Total</span>
                <span class="total-amount">$<?php echo number_format($item['current_high_bid'], 2); ?></span>
            </div>
        </section>

        <section class="checkout-form">
            <h3>Secure Payment by Stripe</h3>
            <p class="security-badge">🔒 Your payment is secure and encrypted</p>

            <div id="stripe-container"></div>
            <button id="checkoutBtn" class="btn btn-primary btn-large">
                <span class="btn-text">Complete Payment</span>
                <span class="btn-spinner" style="display: none;">Processing...</span>
            </button>

            <div id="checkoutError" class="error-message" style="display: none;"></div>

            <p class="return-link">
                <a href="item.php?id=<?php echo $item['id']; ?>">Back to Item</a>
            </p>
        </section>
    </div>

    <script src="js/push-notifications.js"></script>
    <script src="js/app.js"></script>
    <script>
        window.SBB = window.SBB || {};
        window.SBB.itemId = <?php echo (int)$item['id']; ?>;
        window.SBB.sessionToken = localStorage.getItem('session_token');
        window.SBB.amount = <?php echo (float)$item['current_high_bid']; ?>;
    </script>
    <script src="js/stripe-checkout.js"></script>
</body>
