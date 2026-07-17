<?php
// ============================================================
// SILENT BID PRO — Payment Success Page
// Post-checkout confirmation
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db-helpers.php';
require_once __DIR__ . '/includes/public-nav.php';

// Get session ID from query param
$session_id = $_GET['session_id'] ?? '';
$user = getCurrentUser();

// Fetch ALL transactions on this session — a combined checkout pays for
// several items with one Stripe session.
$transactions = dbGetAll(
    "SELECT t.id, t.user_id, t.item_id, t.amount, t.status, i.title, i.event_id
     FROM transactions t
     JOIN items i ON i.id = t.item_id
     WHERE t.stripe_checkout_session_id = ?
     ORDER BY t.id ASC",
    [$session_id]
);

// Only the owning user may view their payment record — don't leak another
// bidder's item/amount to anyone who obtains a session_id.
if ($transactions && (!$user || (int)$transactions[0]['user_id'] !== (int)$user['id'])) {
    $transactions = [];
}
$transaction = $transactions[0] ?? null;

if (!$transaction) {
    renderPublicMessagePage([
        'status' => 404,
        'title' => 'Payment',
        'heading' => 'We could not find that payment record',
        'message' => 'If you just completed checkout, your payment may still be processing. You can return to My Bids to check your item status.',
        'actions' => [
            ['href' => 'my-bids.php', 'label' => 'View My Bids', 'class' => 'btn-primary'],
            ['href' => 'items.php', 'label' => 'Browse Items', 'class' => 'btn-secondary']
        ],
        'user' => $user
    ]);
}

$page_title = 'Payment Successful - ' . APP_NAME;

$total_amount = 0.0;
foreach ($transactions as $t) {
    $total_amount += (float)$t['amount'];
}

// Event-specific pickup/delivery instructions (with a generic default).
require_once __DIR__ . '/includes/fulfillment.php';
$pickup_text = getPickupInstructions((int)($transaction['event_id'] ?? 0));

// The webhook may not have landed yet (status 'pending'), or the payment may
// have actually failed — don't claim success unconditionally.
$is_paid = ($transaction['status'] === 'paid');
$is_failed = in_array($transaction['status'], ['failed', 'cancelled'], true);
if ($is_paid) {
    $success_icon = '🎉';
    $success_heading = 'Thank You!';
    $success_text = 'Your payment has been received.';
    $status_badge_class = 'badge-success';
} elseif ($is_failed) {
    $success_icon = '⚠️';
    $success_heading = 'Payment did not go through';
    $success_text = 'Your payment was not completed. You can try again from My Bids.';
    $status_badge_class = 'badge-error';
} else {
    $success_icon = '⏳';
    $success_heading = 'Payment processing';
    $success_text = 'We have received your checkout and are confirming payment. This page will reflect the final status shortly.';
    $status_badge_class = 'badge-warning';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php renderPageMeta([
        'title' => $page_title,
        'description' => 'Thank you for supporting this fundraising auction through Silent Bid Pro.'
    ]); ?>
</head>
<body class="success-page">
    <?php renderPublicHeader(['back_href' => 'items.php', 'back_label' => '← Items', 'user' => $user]); ?>

    <div class="container success-container">
        <section class="success-message">
            <div class="success-icon"><?php echo $success_icon; ?></div>
            <h1><?php echo htmlspecialchars($success_heading); ?></h1>
            <p class="success-text"><?php echo htmlspecialchars($success_text); ?></p>

            <div class="success-details">
                <?php foreach ($transactions as $t): ?>
                    <div class="summary-item">
                        <span class="item-name"><?php echo htmlspecialchars($t['title']); ?></span>
                        <span class="item-amount">$<?php echo number_format($t['amount'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
                <p class="amount">Total: $<?php echo number_format($total_amount, 2); ?></p>
                <p class="status">
                    Status: <span class="badge <?php echo $status_badge_class; ?>">
                        <?php echo ucfirst($transaction['status']); ?>
                    </span>
                </p>
            </div>

            <div class="next-steps">
                <h3>📦 Getting Your Item<?php echo count($transactions) === 1 ? '' : 's'; ?></h3>
                <p class="pickup-instructions"><?php echo nl2br(htmlspecialchars($pickup_text)); ?></p>
            </div>

            <div class="action-buttons">
                <a href="items.php" class="btn btn-primary btn-large">Back to Auction</a>
                <a href="my-bids.php" class="btn btn-secondary">View My Bids</a>
            </div>
        </section>
    </div>
</body>
