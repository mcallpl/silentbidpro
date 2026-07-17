<?php
// ============================================================
// SILENT BID PRO — Checkout Page
// Stripe Checkout integration
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db-helpers.php';
require_once __DIR__ . '/includes/public-nav.php';
require_once __DIR__ . '/includes/branding-helper.php';

// Require authentication
$user = getCurrentUser();
if (!$user && !empty($_GET['auth_token'])) {
    // Session handoff from the native app: the iOS app opens checkout in an
    // in-app browser that has no cookie for us — accept the app's session
    // token once, re-issue it as the HttpOnly cookie, and clean the URL
    // (same pattern as item.php; the token never stays in the address bar).
    $token = validateSessionToken($_GET['auth_token']);
    if ($token) {
        setSessionCookie(SESSION_COOKIE_NAME, $_GET['auth_token']);
        $clean = strtok($_SERVER['REQUEST_URI'], '?');
        $qs = $_GET;
        unset($qs['auth_token']);
        header('Location: ' . $clean . (!empty($qs) ? ('?' . http_build_query($qs)) : ''));
        exit;
    }
}
if (!$user) {
    header('Location: bid.php?return=' . urlencode($_SERVER['REQUEST_URI'] ?? 'checkout.php'));
    exit;
}

// COMBINED MODE (?all=1): pay for EVERY unpaid won item at once — one Stripe
// payment, one receipt. This is the fallback path when auto-charge couldn't
// run (no saved card, or the card declined).
$combined = !empty($_GET['all']);
$combined_rows = [];
$combined_event_id = 0;
$test_mode = defined('TEST_CHARGE_DOLLAR') && TEST_CHARGE_DOLLAR;
if ($combined) {
    $combined_event_id = (int)($_GET['event'] ?? 0);
    $sql = "SELECT t.id AS tx_id, t.amount, i.id AS item_id, i.title, i.event_id
            FROM transactions t
            JOIN items i ON i.id = t.item_id
            WHERE t.user_id = ? AND t.status = 'pending'
              AND i.is_closed = 1 AND i.current_high_bidder_id = t.user_id";
    $params = [(int)$user['id']];
    if ($combined_event_id) {
        $sql .= " AND i.event_id = ?";
        $params[] = $combined_event_id;
    }
    $combined_rows = dbGetAll($sql . " ORDER BY t.id ASC", $params);

    if (!$combined_rows) {
        renderPublicMessagePage([
            'status' => 404,
            'title' => 'Checkout',
            'heading' => 'Nothing awaiting payment',
            'message' => 'You have no unpaid won items right now. Anything you win will show up here and in My Bids.',
            'actions' => [
                ['href' => 'my-bids.php', 'label' => 'View My Bids', 'class' => 'btn-primary'],
                ['href' => 'items.php', 'label' => 'Browse Items', 'class' => 'btn-secondary']
            ],
            'user' => $user
        ]);
    }
    if (!$combined_event_id) {
        $combined_event_id = (int)$combined_rows[0]['event_id'];
    }
}

// Get item ID
$item_id = $_GET['item_id'] ?? 0;
if (!$item_id && !$combined) {
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

// Fetch item (single-item mode)
$item = null;
if (!$combined) {
    $item = dbGetRow(
        "SELECT id, title, current_high_bid, current_high_bidder_id, is_closed FROM items WHERE id = ?",
        [(int)$item_id]
    );
}

// Payment is only available to the winning bidder AND only after the auction
// has closed (mirrors the server-side guard in create-session.php).
if (!$combined && (!$item || $item['current_high_bidder_id'] != $user['id'] || empty($item['is_closed']))) {
    renderPublicMessagePage([
        'status' => 403,
        'title' => 'Checkout',
        'heading' => 'This checkout is not available',
        'message' => 'Payment opens to the winning bidder once the auction closes. Check My Bids for items ready for payment.',
        'actions' => [
            ['href' => 'my-bids.php', 'label' => 'View My Bids', 'class' => 'btn-primary'],
            ['href' => 'items.php', 'label' => 'Browse Items', 'class' => 'btn-secondary']
        ],
        'user' => $user
    ]);
}

$page_title = 'Checkout - ' . APP_NAME;
$branding = getBrandingData();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php renderPageMeta([
        'title' => $page_title,
        'description' => 'Complete your secure Silent Bid Pro auction payment.'
    ]); ?>
    <script src="https://js.stripe.com/v3/"></script>
</head>
<body class="checkout-page" data-vapid-public-key="<?php echo htmlspecialchars(VAPID_PUBLIC_KEY); ?>">
    <?php renderPublicHeader([
        'title' => APP_NAME . ' - Payment',
        'back_href' => $combined ? 'my-bids.php' : 'item.php?id=' . (int)$item['id'],
        'back_label' => $combined ? '← My Bids' : '← Item',
        'user' => $user
    ]); ?>

    <div class="container checkout-container">
        <?php if ($branding): ?>
            <?php renderEventBanner(['show_logo' => false, 'show_mission' => false]); ?>
        <?php endif; ?>

        <section class="checkout-summary">
            <h2>Order Summary</h2>
            <?php if ($combined): ?>
                <?php
                    $combined_total = 0.0;
                    foreach ($combined_rows as $row):
                        $line = $test_mode ? 1.00 : (float)$row['amount'];
                        $combined_total += $line;
                ?>
                    <div class="summary-item">
                        <span class="item-name"><?php echo htmlspecialchars($row['title']); ?></span>
                        <span class="item-amount">$<?php echo number_format($line, 2); ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="summary-total">
                    <span class="total-label">Total (<?php echo count($combined_rows); ?> item<?php echo count($combined_rows) === 1 ? '' : 's'; ?>)</span>
                    <span class="total-amount">$<?php echo number_format($combined_total, 2); ?></span>
                </div>
            <?php else: ?>
                <div class="summary-item">
                    <span class="item-name"><?php echo htmlspecialchars($item['title']); ?></span>
                    <span class="item-amount">$<?php echo number_format($item['current_high_bid'], 2); ?></span>
                </div>
                <div class="summary-total">
                    <span class="total-label">Total</span>
                    <span class="total-amount">$<?php echo number_format($item['current_high_bid'], 2); ?></span>
                </div>
            <?php endif; ?>
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
                <a href="<?php echo $combined ? 'my-bids.php' : 'item.php?id=' . (int)$item['id']; ?>"><?php echo $combined ? 'Back to My Bids' : 'Back to Item'; ?></a>
            </p>
        </section>
    </div>

    <script src="js/push-notifications.js"></script>
    <script src="js/app.js"></script>
    <script>
        window.SBB = window.SBB || {};
        window.SBB.combinedCheckout = <?php echo $combined ? 'true' : 'false'; ?>;
        window.SBB.eventId = <?php echo (int)$combined_event_id; ?>;
        window.SBB.itemId = <?php echo $combined ? 0 : (int)$item['id']; ?>;
        window.SBB.sessionToken = localStorage.getItem('session_token');
    </script>
    <script src="js/stripe-checkout.js"></script>
</body>
