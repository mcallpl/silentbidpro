<?php
// ============================================================
// SILENT BID BUDDY — Payment Success Page
// Post-checkout confirmation
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db-helpers.php';

// Get session ID from query param
$session_id = $_GET['session_id'] ?? '';

// Fetch transaction by session ID
$transaction = dbGetRow(
    "SELECT t.id, t.item_id, t.amount, t.status, i.title
     FROM transactions t
     JOIN items i ON i.id = t.item_id
     WHERE t.stripe_checkout_session_id = ?",
    [$session_id]
);

if (!$transaction) {
    http_response_code(404);
    die('Transaction not found');
}

$page_title = 'Payment Successful - ' . APP_NAME;
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
<body class="success-page">
    <header class="app-header">
        <h1><?php echo APP_NAME; ?></h1>
    </header>

    <div class="container success-container">
        <section class="success-message">
            <div class="success-icon">🎉</div>
            <h1>Thank You!</h1>
            <p class="success-text">Your payment has been received.</p>

            <div class="success-details">
                <h3><?php echo htmlspecialchars($transaction['title']); ?></h3>
                <p class="amount">$<?php echo number_format($transaction['amount'], 2); ?></p>
                <p class="status">
                    Status: <span class="badge badge-success">
                        <?php echo ucfirst($transaction['status']); ?>
                    </span>
                </p>
            </div>

            <div class="next-steps">
                <h3>What's Next?</h3>
                <ol>
                    <li>Watch for SMS updates about your item</li>
                    <li>Arrange pickup or delivery with the nonprofit</li>
                    <li>Enjoy knowing your donation supports a great cause!</li>
                </ol>
            </div>

            <div class="action-buttons">
                <a href="/silentbidbuddy/index.php" class="btn btn-primary btn-large">Back to Auction</a>
                <a href="#" class="btn btn-secondary">Download Receipt</a>
            </div>
        </section>
    </div>
</body>
