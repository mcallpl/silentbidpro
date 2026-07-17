<?php
// ============================================================
// SILENT BID PRO — Public Landing Page
// Professional front door for bidders, nonprofits, and admins.
// ============================================================

// PUBLIC FRONT DOOR: the homepage always shows the currently OPEN event's
// branding, even to a browser session pinned to a private draft/closed test
// auction. (Bidding pages keep honoring the pin — that isolation is a feature.)
define('SBB_PUBLIC_FRONT_DOOR', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/page-meta.php';
require_once __DIR__ . '/includes/branding-helper.php';

// Never let a browser cache serve a stale event banner.
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$page_title = APP_NAME . ' - Silent Auctions Made Warm, Simple, and Professional';
$branding = getBrandingData();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php renderPageMeta([
        'title' => $page_title,
        'description' => 'Silent Bid Pro helps nonprofits run polished silent auctions with beautiful item pages, secure bidding, bidder reminders, admin tools, and donor-friendly checkout.'
    ]); ?>
</head>
<body class="landing-page">
    <header class="landing-nav" aria-label="Silent Bid Pro navigation">
        <a class="landing-brand" href="index.php" aria-label="Silent Bid Pro home">
            <img src="images/brand/favicon.svg" alt="" />
            <span>Silent Bid Pro</span>
        </a>
        <nav class="landing-nav-links" aria-label="Primary links">
            <a href="#experience">Experience</a>
            <a href="#tools">Admin Tools</a>
            <a href="items.php">Open Auction</a>
            <a class="nav-admin-link" href="admin.php">Admin</a>
        </nav>
    </header>

    <main>
        <?php if ($branding): ?>
            <?php renderEventBanner(['show_logo' => true, 'show_mission' => true]); ?>
        <?php endif; ?>

        <section class="landing-hero" aria-labelledby="landingHeroTitle">
            <div class="landing-hero-bg" aria-hidden="true"></div>
            <div class="landing-hero-content">
                <p class="landing-kicker">Nonprofit silent auctions, handled beautifully</p>
                <h1 id="landingHeroTitle">
                    <span>Bid with</span>
                    <span>confidence.</span>
                    <span>Give with</span>
                    <span>heart.</span>
                </h1>
                <p class="landing-hero-copy">
                    Silent Bid Pro gives your auction a polished digital home, helps bidders feel informed,
                    and gives administrators the practical tools they need to run the room with calm.
                </p>
                <div class="landing-actions" aria-label="Primary actions">
                    <a class="landing-btn landing-btn-primary" href="items.php">Open Auction App</a>
                    <a class="landing-btn landing-btn-secondary" href="bid.php">Bidder Sign In</a>
                    <a class="landing-btn landing-btn-quiet" href="admin.php">Admin Console</a>
                    <!-- Revealed by the app-store availability check at the foot of the page -->
                    <a class="app-store-badge" data-appstore-badge hidden
                       href="https://apps.apple.com/us/app/id6787838881"
                       aria-label="Download Silent Bid Pro on the App Store">
                        <img src="images/brand/app-store-badge.svg" alt="Download on the App Store" height="52">
                    </a>
                </div>
            </div>
            <div class="landing-hero-proof" aria-label="Platform highlights">
                <div>
                    <strong>Live bidding</strong>
                    <span>Clear current bids, opening bids, and bidder status.</span>
                </div>
                <div>
                    <strong>Beautiful items</strong>
                    <span>Rich descriptions, generated artwork, and share-ready presentation.</span>
                </div>
                <div>
                    <strong>Admin control</strong>
                    <span>Manage items, users, close times, payments, and auction flow.</span>
                </div>
            </div>
        </section>

        <section id="experience" class="landing-band landing-intro" aria-labelledby="experienceTitle">
            <div class="landing-section-heading">
                <p class="landing-kicker">Designed for the whole room</p>
                <h2 id="experienceTitle">A calmer bidder experience and a sharper event presence.</h2>
            </div>
            <div class="landing-feature-grid">
                <article class="landing-feature">
                    <span class="feature-number">01</span>
                    <h3>Guests know what to do next.</h3>
                    <p>Friendly phone verification, simple item browsing, clear bid states, watchlists, and checkout paths keep the evening moving.</p>
                </article>
                <article class="landing-feature">
                    <span class="feature-number">02</span>
                    <h3>Items look worth bidding on.</h3>
                    <p>Descriptions can be improved for donor appeal, and item artwork can be created from the story behind each donation.</p>
                </article>
                <article class="landing-feature">
                    <span class="feature-number">03</span>
                    <h3>The auction feels trustworthy.</h3>
                    <p>Bidder status, closing times, receipts, payments, and item details are presented with the polish people expect from a serious platform.</p>
                </article>
            </div>
        </section>

        <section id="tools" class="landing-band landing-admin-panel" aria-labelledby="adminTitle">
            <div class="admin-copy">
                <p class="landing-kicker">For administrators</p>
                <h2 id="adminTitle">Run the auction from one purposeful console.</h2>
                <p>
                    Build item listings, improve descriptions, manage bidders, review bids, create documents,
                    monitor payments, and close items without making the team work from scattered spreadsheets.
                </p>
                <div class="admin-link-row">
                    <a class="landing-btn landing-btn-primary" href="admin.php">Open Admin Console</a>
                    <a class="landing-text-link" href="items.php">Preview bidder app</a>
                </div>
            </div>
            <div class="admin-metrics" aria-label="Administrative capabilities">
                <div><strong>Items</strong><span>Descriptions, imagery, values, and close times</span></div>
                <div><strong>Bidders</strong><span>Phone verification, emails, watchlists, and activity</span></div>
                <div><strong>Payments</strong><span>Winning bids, checkout, receipts, and status</span></div>
                <div><strong>Materials</strong><span>Share links, item pages, QR codes, and PDFs</span></div>
            </div>
        </section>

        <section class="landing-band landing-cta" aria-labelledby="landingCtaTitle">
            <p class="landing-kicker">Ready when the doors open</p>
            <h2 id="landingCtaTitle">Give your bidders a professional first impression.</h2>
            <p>Start in the auction app, sign in as a bidder, or step into the admin console.</p>
            <div class="landing-actions">
                <a class="landing-btn landing-btn-primary" href="items.php">Open Auction App</a>
                <a class="landing-btn landing-btn-secondary" href="bid.php">Bidder Sign In</a>
                <a class="landing-btn landing-btn-quiet" href="admin.php">Admin Console</a>
                <a class="app-store-badge" data-appstore-badge hidden
                   href="https://apps.apple.com/us/app/id6787838881"
                   aria-label="Download Silent Bid Pro on the App Store">
                    <img src="images/brand/app-store-badge.svg" alt="Download on the App Store" height="52">
                </a>
            </div>
        </section>
    </main>

    <script>
    // Reveal the App Store badges only once the listing is actually live in
    // Apple's catalog (JSONP against the public iTunes Lookup API), so the
    // homepage never shows a dead store link while propagation completes.
    function sbpAppStoreCheck(data) {
        if (data && data.resultCount > 0) {
            document.querySelectorAll('[data-appstore-badge]').forEach(function (el) {
                el.hidden = false;
            });
        }
    }
    (function () {
        var s = document.createElement('script');
        s.src = 'https://itunes.apple.com/lookup?id=6787838881&country=us&callback=sbpAppStoreCheck';
        s.async = true;
        document.body.appendChild(s);
    })();
    </script>

    <footer class="landing-footer">
        <span><?php echo htmlspecialchars(APP_NAME); ?></span>
        <span>Secure bidding for generous events.</span>
    </footer>
</body>
</html>
