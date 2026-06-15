<?php
// ============================================================
// PUBLIC NAVIGATION
// Shared bidder-facing header and menu.
// ============================================================

require_once __DIR__ . '/page-meta.php';

function renderPublicHeader($options = []) {
    $title = $options['title'] ?? APP_NAME;
    $back_href = $options['back_href'] ?? null;
    $back_label = $options['back_label'] ?? 'Back';
    $user = $options['user'] ?? getCurrentUser();
    $is_authenticated = $user !== false;
    $current = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
    ?>
    <header class="app-header">
        <div class="header-side header-side-left">
            <?php if ($back_href): ?>
                <a class="btn-back" href="<?php echo htmlspecialchars($back_href); ?>"><?php echo htmlspecialchars($back_label); ?></a>
            <?php endif; ?>
        </div>

        <h1><?php echo htmlspecialchars($title); ?></h1>

        <button
            type="button"
            class="btn-menu js-public-menu-toggle"
            aria-label="Open menu"
            aria-controls="publicMenu"
            aria-expanded="false"
        >
            <span aria-hidden="true">☰</span>
        </button>
    </header>

    <div id="publicMenuOverlay" class="public-menu-overlay" hidden></div>
    <nav id="publicMenu" class="public-menu" aria-label="Bidder menu" hidden>
        <div class="public-menu-header">
            <div>
                <strong><?php echo htmlspecialchars(APP_NAME); ?></strong>
                <span><?php echo $is_authenticated ? htmlspecialchars($user['full_name'] ?: 'Signed in bidder') : 'Auction menu'; ?></span>
            </div>
            <button type="button" class="public-menu-close js-public-menu-close" aria-label="Close menu">×</button>
        </div>

        <a class="<?php echo $current === 'items.php' ? 'active' : ''; ?>" href="items.php">Browse Items</a>
        <?php if ($is_authenticated): ?>
            <a class="<?php echo $current === 'my-bids.php' ? 'active' : ''; ?>" href="my-bids.php">My Bids & Watching</a>
        <?php else: ?>
            <a href="bid.php?return=<?php echo urlencode('items.php'); ?>">Sign In to Bid</a>
        <?php endif; ?>
        <a href="items.php#how-bidding-works">How Bidding Works</a>

        <?php if ($is_authenticated): ?>
            <button type="button" class="public-menu-action js-public-logout">Sign Out</button>
        <?php endif; ?>
    </nav>
    <?php
}

function renderPublicMessagePage($options = []) {
    $status = (int)($options['status'] ?? 200);
    $title = $options['title'] ?? APP_NAME;
    $heading = $options['heading'] ?? 'Something needs attention';
    $message = $options['message'] ?? 'Please return to the auction and try again.';
    $actions = $options['actions'] ?? [
        ['href' => 'items.php', 'label' => 'Browse Items', 'class' => 'btn-primary']
    ];
    $user = $options['user'] ?? getCurrentUser();

    http_response_code($status);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <?php renderPageMeta([
            'title' => $title . ' - ' . APP_NAME,
            'description' => $message
        ]); ?>
    </head>
    <body class="items-list-page message-page" data-vapid-public-key="<?php echo htmlspecialchars(VAPID_PUBLIC_KEY); ?>">
        <?php renderPublicHeader(['user' => $user]); ?>
        <main class="container">
            <section class="public-message-card">
                <h1><?php echo htmlspecialchars($heading); ?></h1>
                <p><?php echo htmlspecialchars($message); ?></p>
                <div class="public-message-actions">
                    <?php foreach ($actions as $action): ?>
                        <a
                            href="<?php echo htmlspecialchars($action['href']); ?>"
                            class="btn <?php echo htmlspecialchars($action['class'] ?? 'btn-secondary'); ?>"
                        >
                            <?php echo htmlspecialchars($action['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
        <script src="js/push-notifications.js"></script>
        <script src="js/app.js"></script>
    </body>
    </html>
    <?php
    exit;
}
?>
