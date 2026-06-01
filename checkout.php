<?php
// ============================================================
// SILENT BID BUDDY — Checkout Page
// Stripe Checkout integration
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db-helpers.php';

// Require authentication
$user = getCurrentUser();
if (!$user) {
    header('Location: /silentbidbuddy/index.php');
    exit;
}

// Get item ID
$item_id = $_GET['item_id'] ?? 0;
if (!$item_id) {
    die('Item not found');
}

// Fetch item
$item = dbGetRow(
    "SELECT id, title, current_high_bid, current_high_bidder_id FROM items WHERE id = ?",
    [(int)$item_id]
);

if (!$item || $item['current_high_bidder_id'] != $user['id']) {
    http_response_code(403);
    die('You are not the winner of this item');
}

$page_title = 'Checkout - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/silentbidbuddy/css/main.css">
    <link rel="stylesheet" href="/silentbidbuddy/css/mobile.css">
    <script src="https://js.stripe.com/v3/"></script>
</head>
<body class="checkout-page">
    <header class="app-header">
        <h1><?php echo APP_NAME; ?> — Payment</h1>
    </header>

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
                <a href="/silentbidbuddy/item.php?id=<?php echo $item['id']; ?>">Back to Item</a>
            </p>
        </section>
    </div>

    <script src="/silentbidbuddy/js/app.js"></script>
    <script>
        window.SBB = window.SBB || {};
        window.SBB.itemId = <?php echo (int)$item['id']; ?>;
        window.SBB.sessionToken = localStorage.getItem('session_token');
        window.SBB.amount = <?php echo (float)$item['current_high_bid']; ?>;
    </script>
    <script src="/silentbidbuddy/js/stripe-checkout.js"></script>
</body>
