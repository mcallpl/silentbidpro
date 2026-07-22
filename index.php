<?php
// ============================================================
// SILENT BID PRO — Public Landing Page
// Enterprise product landing page for nonprofits, schools,
// foundations, universities, and mission-driven organizations.
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

$page_title = APP_NAME . ' - Your Auction. Your Brand. More Impact.';
$branding = getBrandingData();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php renderPageMeta([
        'title' => $page_title,
        'description' => 'Silent Bid Pro is a customizable online auction platform for nonprofits, schools, foundations, universities, and associations. Create a branded auction your guests enjoy, then manage bidding, payments, and results from one place.',
        'stylesheets' => ['css/branding-variables.css', 'css/main.css', 'css/branding.css', 'css/mobile.css', 'css/landing.css'],
    ]); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="sbp-landing">
<a class="sbp-skip-link" href="#sbp-main">Skip to content</a>

<!-- ============================================================
     SECTION 1 — GLOBAL NAVIGATION
     ============================================================ -->
<header class="sbp-nav" id="sbpNav" aria-label="Primary navigation">
    <div class="sbp-wrap">
        <a class="sbp-nav-logo" href="index.php" aria-label="Silent Bid Pro home">
            <img src="images/brand/silentbidpro-logo-black.png" alt="Silent Bid Pro" width="160" height="40">
        </a>
        <nav class="sbp-nav-links" aria-label="Sections">
            <a href="#platform">Platform</a>
            <a href="#how-it-works">How It Works</a>
            <a href="#for-bidders">For Bidders</a>
            <a href="#for-organizations">For Organizations</a>
            <a href="#pricing">Pricing</a>
            <a href="#resources">Resources</a>
        </nav>
        <div class="sbp-nav-right">
            <a class="sbp-nav-signin" href="bid.php">Sign In</a>
            <a class="sbp-btn sbp-btn-primary" href="#request-demo">Request a Demo</a>
            <button class="sbp-nav-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="sbpDrawer" data-drawer-open>
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<!-- Mobile drawer -->
<div class="sbp-drawer" id="sbpDrawer" role="dialog" aria-modal="true" aria-label="Menu" aria-hidden="true">
    <div class="sbp-drawer-scrim" data-drawer-close></div>
    <div class="sbp-drawer-panel">
        <div class="sbp-drawer-head">
            <img src="images/brand/silentbidpro-logo-dark.png" alt="Silent Bid Pro">
            <button class="sbp-drawer-close" type="button" aria-label="Close menu" data-drawer-close>&times;</button>
        </div>
        <a href="#platform" data-drawer-close>Platform</a>
        <a href="#how-it-works" data-drawer-close>How It Works</a>
        <a href="#for-bidders" data-drawer-close>For Bidders</a>
        <a href="#for-organizations" data-drawer-close>For Organizations</a>
        <a href="#pricing" data-drawer-close>Pricing</a>
        <a href="#resources" data-drawer-close>Resources</a>
        <a href="bid.php" data-drawer-close>Sign In</a>
        <a class="sbp-btn sbp-btn-primary" href="#request-demo" data-drawer-close>Request a Demo</a>
    </div>
</div>

<main id="sbp-main">

<!-- ============================================================
     SECTION 2 — HERO
     ============================================================ -->
<section class="sbp-hero" id="platform" aria-labelledby="heroTitle">
    <div class="sbp-wrap">
        <div class="sbp-hero-text">
            <span class="sbp-hero-badge">Custom auctions for mission-driven organizations</span>
            <h1 id="heroTitle" class="sbp-serif">
                Your auction.<br>
                Your brand.<br>
                <span class="accent">More</span> impact.
            </h1>
            <p class="sbp-hero-copy">
                Create a beautifully branded auction experience your guests love. Then manage bidding, payments, and results from one central command center.
            </p>
            <div class="sbp-hero-actions">
                <button class="sbp-btn sbp-btn-primary sbp-btn-lg" type="button" data-modal-open="liveModal">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="currentColor" opacity="0.25"/><path d="M10 8l6 4-6 4V8z" fill="currentColor"/></svg>
                    Watch a Live Auction
                </button>
                <button class="sbp-btn sbp-btn-secondary sbp-btn-lg on-dark" type="button" data-modal-open="dashModal">
                    Explore the Dashboard
                </button>
            </div>
            <div class="sbp-hero-props" aria-label="Platform highlights">
                <div class="sbp-hero-prop">
                    <span class="sbp-hero-prop-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18M3 12h18"/></svg></span>
                    <strong>Branded your way</strong>
                    <span>Custom to your mission</span>
                </div>
                <div class="sbp-hero-prop">
                    <span class="sbp-hero-prop-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3"/><path d="M4 20c0-3 2-5 5-5s5 2 5 5M17 11l2 2 3-3"/></svg></span>
                    <strong>Loved by guests</strong>
                    <span>Easy and mobile first</span>
                </div>
                <div class="sbp-hero-prop">
                    <span class="sbp-hero-prop-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 18l5-5 3 3 7-8"/><path d="M16 8h4v4"/></svg></span>
                    <strong>All in one control</strong>
                    <span>Plan, manage, and run</span>
                </div>
                <div class="sbp-hero-prop">
                    <span class="sbp-hero-prop-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6l7-3z"/></svg></span>
                    <strong>Secure and reliable</strong>
                    <span>Payments handled safely</span>
                </div>
            </div>
            <div class="sbp-hero-appstore">
                <a class="app-store-badge" data-appstore-badge hidden
                   href="https://apps.apple.com/us/app/id6787838881"
                   aria-label="Download Silent Bid Pro on the App Store">
                    <img src="images/brand/app-store-badge.svg" alt="Download on the App Store" height="44">
                </a>
            </div>
        </div>

        <!-- Composed product visual -->
        <div class="sbp-hero-stage">
            <div class="sbp-screen" role="img" aria-label="Desktop preview of a branded auction homepage showing 128 percent of goal and 128,450 dollars raised with featured items">
                <div class="sbp-screen-top" aria-hidden="true">
                    <span class="sbp-dot"></span><span class="sbp-dot"></span><span class="sbp-dot"></span>
                    <span class="sbp-screen-url">greenfieldfund.silentbidpro.com</span>
                </div>
                <div class="sbp-orgbar">
                    <span class="sbp-orgbar-brand"><span class="sbp-orgbar-mark">GE</span>Greenfield Education Fund</span>
                    <span class="sbp-orgbar-nav"><span>Home</span><span>Items</span><span>Donate</span><span class="pill">Sign In</span></span>
                </div>
                <div class="sbp-hero-banner">
                    <img src="images/items/web/gala.jpg" alt="" loading="lazy" width="576" height="150">
                    <span class="sbp-hero-sample">Sample</span>
                    <div class="sbp-hero-banner-cap">
                        <h4 class="sbp-serif">Spring Gala Silent Auction</h4>
                        <span>May 3 - May 10, 2025</span>
                    </div>
                </div>
                <div class="sbp-hood-strip">
                    <div class="sbp-ring" aria-hidden="true">
                        <svg width="58" height="58" viewBox="0 0 66 66"><circle cx="33" cy="33" r="28" fill="none" stroke="#e5e7eb" stroke-width="6"/><circle cx="33" cy="33" r="28" fill="none" stroke="#1B5E3B" stroke-width="6" stroke-linecap="round" stroke-dasharray="176" stroke-dashoffset="0"/></svg>
                        <span class="sbp-ring-label"><b>128%</b><span>OF GOAL</span></span>
                    </div>
                    <div class="sbp-goal-fig">
                        <b>$128,450</b>
                        <small>Raised of $100,000 goal</small>
                    </div>
                    <span class="sbp-hood-cta">Browse Items</span>
                </div>
                <div class="sbp-feat">
                    <div class="sbp-feat-head"><b>Featured Items</b><a href="#">View all items</a></div>
                    <div class="sbp-feat-grid">
                        <div class="sbp-feat-card"><div class="sbp-feat-thumb"><img src="images/items/web/wine.jpg" alt="" loading="lazy" width="140" height="90"></div><div class="sbp-feat-body"><b>Napa Vineyard Weekend</b><span class="price">$1,250</span> <span class="bids">12 bids</span></div></div>
                        <div class="sbp-feat-card"><div class="sbp-feat-thumb"><img src="images/items/web/cabin.jpg" alt="" loading="lazy" width="140" height="90"></div><div class="sbp-feat-body"><b>Mountain Cabin Retreat</b><span class="price">$950</span> <span class="bids">8 bids</span></div></div>
                        <div class="sbp-feat-card"><div class="sbp-feat-thumb"><img src="images/items/web/theater.jpg" alt="" loading="lazy" width="140" height="90"></div><div class="sbp-feat-body"><b>VIP Theater Night</b><span class="price">$620</span> <span class="bids">6 bids</span></div></div>
                        <div class="sbp-feat-card"><div class="sbp-feat-thumb"><img src="images/items/web/spa.jpg" alt="" loading="lazy" width="140" height="90"></div><div class="sbp-feat-body"><b>Spa &amp; Wellness Day</b><span class="price">$475</span> <span class="bids">5 bids</span></div></div>
                    </div>
                </div>
            </div>

            <div class="sbp-hero-phone" aria-hidden="true">
                <div class="sbp-phone-screen">
                    <span class="sbp-phone-island"></span>
                    <div class="sbp-phone-head"><h5 class="sbp-serif">Spring Gala</h5><span>May 3 - May 10, 2025</span></div>
                    <div class="sbp-phone-goal">
                        <span class="sbp-phone-badge"><b>128%</b><span>OF GOAL</span></span>
                        <span class="fig"><b>$128,450</b><span>Raised of $100,000</span></span>
                    </div>
                    <div class="sbp-phone-cta">Browse Items</div>
                    <div class="sbp-phone-timer"><span>Auction ends in</span><b>02 : 15 : 47</b></div>
                    <div class="sbp-phone-item">
                        <span class="sbp-phone-item-thumb"><img src="images/items/web/wine.jpg" alt=""></span>
                        <span class="sbp-phone-item-info"><b>Napa Vineyard Weekend</b><span>Current bid $1,250</span></span>
                    </div>
                    <div class="sbp-phone-item">
                        <span class="sbp-phone-item-thumb"><img src="images/items/web/theater.jpg" alt=""></span>
                        <span class="sbp-phone-item-info"><b>VIP Theater Night</b><span>Current bid $620</span></span>
                    </div>
                    <div class="sbp-phone-tabs"><span></span><span></span><span></span><span></span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     LIVE AUCTION PULSE — live-stats band directly under the hero
     ============================================================ -->
<section class="sbp-pulse" id="how-it-works" aria-labelledby="pulseTitle">
    <div class="sbp-wrap">
        <h2 id="pulseTitle" class="sbp-pulse-live"><span class="sbp-pulse-dot" aria-hidden="true"></span>Live Auction Pulse</h2>
        <div class="sbp-pulse-metrics">
            <div class="sbp-pulse-metric">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3 3-5 6-5s6 2 6 5M16 6a3 3 0 010 6M21 20c0-2-1-3-3-4"/></svg>
                <span><b>421</b><span>Guests Online</span></span>
            </div>
            <div class="sbp-pulse-metric">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 4l6 6-9 9-6 1 1-6 8-10z"/></svg>
                <span><b>2,184</b><span>Bids Today</span></span>
            </div>
            <div class="sbp-pulse-metric">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21C7 17 3 13 3 8.5A4.5 4.5 0 0112 6a4.5 4.5 0 019 2.5C21 13 17 17 12 21z"/></svg>
                <span><b class="accent">128%</b><span>Of Goal</span></span>
            </div>
            <div class="sbp-pulse-metric">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                <span><b>$128,450</b><span>Raised</span></span>
            </div>
            <div class="sbp-pulse-metric">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                <span><b>12</b><span>Items Closing Soon</span></span>
            </div>
        </div>
        <div class="sbp-pulse-foot">
            <button class="sbp-btn sbp-btn-primary" type="button" data-modal-open="liveModal">
                Watch Live Now
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
        </div>
        <p class="sbp-pulse-disclaimer">Product Demonstration Data</p>
    </div>
</section>

<!-- ============================================================
     SECTION 3 — ORGANIZATION PERSONALIZATION
     ============================================================ -->
<section class="sbp-section alt" id="for-organizations" aria-labelledby="orgTitle">
    <div class="sbp-wrap">
        <div class="sbp-head">
            <p class="sbp-eyebrow">White-label branding control</p>
            <h2 id="orgTitle" class="sbp-serif">Your brand takes center stage.</h2>
            <p>Customize colors, typography, logos, and imagery so your auction feels like a natural extension of your organization.</p>
        </div>

        <div class="sbp-switcher" data-brand-switcher>
            <div class="sbp-switch-tabs" role="tablist" aria-label="Branded auction examples">
                <button class="sbp-switch-tab is-active" role="tab" id="brandtab-health" aria-controls="brandPreview" aria-selected="true" tabindex="0" data-theme="health">Children's Health</button>
                <button class="sbp-switch-tab" role="tab" id="brandtab-rescue" aria-controls="brandPreview" aria-selected="false" tabindex="-1" data-theme="rescue">Animal Rescue</button>
                <button class="sbp-switch-tab" role="tab" id="brandtab-gala" aria-controls="brandPreview" aria-selected="false" tabindex="-1" data-theme="gala">University Gala</button>
                <button class="sbp-switch-tab" role="tab" id="brandtab-conservation" aria-controls="brandPreview" aria-selected="false" tabindex="-1" data-theme="conservation">Conservation</button>
            </div>

            <div class="sbp-switch-preview" id="brandPreview" role="tabpanel" aria-labelledby="brandtab-health" aria-live="polite" tabindex="0">
                <div class="sbp-switch-inner" data-preview-inner>
                    <div class="sbp-switch-banner">
                        <span class="sbp-switch-sample">Sample</span>
                        <p class="sbp-switch-org"><span class="mk" data-p-initials>CH</span><span data-p-org>Children's Health Foundation</span></p>
                        <h3 class="sbp-serif" data-p-title>Healing Starts Here</h3>
                    </div>
                    <div class="sbp-switch-foot">
                        <div class="sbp-switch-metric">
                            <b data-p-pct>142%</b>
                            <span>Of goal raised</span>
                        </div>
                        <div class="sbp-switch-actions">
                            <span class="sbp-switch-fig"><b data-p-amount>$284,000</b><small>Raised</small></span>
                            <button class="sbp-switch-btn" type="button" data-p-btn>Preview Experience</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <p class="sbp-note" style="text-align:center; margin-top:34px;">Illustrative examples of branded auction experiences.</p>
    </div>
</section>

<!-- ============================================================
     SECTION 4 — BIDDER EXPERIENCE
     ============================================================ -->
<section class="sbp-section" id="for-bidders" aria-labelledby="bidderTitle">
    <div class="sbp-wrap">
        <div class="sbp-head">
            <p class="sbp-eyebrow">Frictionless bidder journey</p>
            <h2 id="bidderTitle" class="sbp-serif">Designed for every guest. On any device.</h2>
            <p>Zero app downloads required. Guests scan a QR code, browse items, place bids, and check out from their mobile browser.</p>
        </div>

        <div class="sbp-stepper" data-stepper>
            <!-- Left: progress tracker + narrative -->
            <div class="sbp-stepper-left">
                <div class="sbp-step-tracker" role="tablist" aria-label="Bidder journey steps" aria-orientation="vertical">
                    <button class="sbp-track-item is-active" role="tab" id="btrack-0" aria-selected="true" aria-controls="bidderPanel" tabindex="0" data-step="0">
                        <span class="sbp-track-dot"></span>
                        <span class="sbp-track-txt"><b>Check-In</b><span>Register and access the auction in one tap</span></span>
                    </button>
                    <button class="sbp-track-item" role="tab" id="btrack-1" aria-selected="false" aria-controls="bidderPanel" tabindex="-1" data-step="1">
                        <span class="sbp-track-dot"></span>
                        <span class="sbp-track-txt"><b>Browse &amp; Bid</b><span>Discover items and place instant bids</span></span>
                    </button>
                    <button class="sbp-track-item" role="tab" id="btrack-2" aria-selected="false" aria-controls="bidderPanel" tabindex="-1" data-step="2">
                        <span class="sbp-track-dot"></span>
                        <span class="sbp-track-txt"><b>Real-Time Alerts</b><span>Instant notifications the moment you are outbid</span></span>
                    </button>
                    <button class="sbp-track-item" role="tab" id="btrack-3" aria-selected="false" aria-controls="bidderPanel" tabindex="-1" data-step="3">
                        <span class="sbp-track-dot"></span>
                        <span class="sbp-track-txt"><b>One-Tap Checkout</b><span>Secure payment with Apple Pay, Google Pay, or card</span></span>
                    </button>
                </div>

                <div class="sbp-step-narrative" id="bidderPanel" role="tabpanel" aria-labelledby="btrack-0" aria-live="polite">
                    <div class="sbp-narr-inner" data-bnarrative>
                        <span class="sbp-narr-count"><b data-bn-num>01</b> / 04</span>
                        <h3 class="sbp-serif" data-bn-title>Check-In</h3>
                        <p data-bn-desc>Guests join instantly. A one-tap magic link gets them into your branded auction with no app and no password.</p>
                        <ul class="sbp-narr-bullets" data-bn-bullets>
                            <li>SMS magic link</li>
                            <li>No passwords required</li>
                            <li>Instant access</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Right: frameless floating mobile screen -->
            <div class="sbp-stepper-right">
                <div class="sbp-appscreen" aria-hidden="true">
                    <div class="sbp-appscreen-status">
                        <span class="time">8:42</span>
                        <span class="sysicons">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M2 16h3v5H2zM7 12h3v9H7zM12 8h3v13h-3zM17 4h3v17h-3z"/></svg>
                            <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M12 4C7 4 3 7 1 11l11 9 11-9c-2-4-6-7-11-7z" opacity=".9"/></svg>
                            <svg viewBox="0 0 26 14" width="20" height="12" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="1" y="1.5" width="20" height="11" rx="3"/><rect x="3" y="3.5" width="13" height="7" rx="1.5" fill="currentColor" stroke="none"/><rect x="22.5" y="5" width="2" height="4" rx="1" fill="currentColor" stroke="none"/></svg>
                        </span>
                    </div>
                    <div class="sbp-appscreen-body" data-bscreen>
                        <!-- Step content injected by JS; initial = Check-In -->
                        <div class="sbp-app-org"><span class="mk">GE</span>Greenfield Education Gala</div>
                        <div class="sbp-app-checkin">
                            <h4 class="sbp-serif">Welcome</h4>
                            <p>Join the auction in seconds.</p>
                            <button class="sbp-app-btn" type="button" tabindex="-1">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16v14H4zM4 7l8 5 8-5"/></svg>
                                Send One-Tap Login Link
                            </button>
                            <div class="sbp-app-or">Or enter phone number</div>
                            <div class="sbp-app-field">(555) 019-2831</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     SECTION 6 — ADMINISTRATIVE COMMAND CENTER
     ============================================================ -->
<section class="sbp-section alt" id="for-administrators" aria-labelledby="adminTitle">
    <div class="sbp-wrap">
        <div class="sbp-head">
            <p class="sbp-eyebrow">Administrative command center</p>
            <h2 id="adminTitle" class="sbp-serif">Run your entire auction from one command center.</h2>
            <p>Give your team total control over items, bidders, live activity, payments, and automated closeout.</p>
        </div>
        <div class="sbp-admin">
            <div class="sbp-admin-props">
                <ul class="sbp-check-list">
                    <li><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M5 12l4 4 10-10"/></svg></span>Live bidding and real-time analytics</li>
                    <li><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M5 12l4 4 10-10"/></svg></span>Automated payments and instant receipts</li>
                    <li><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M5 12l4 4 10-10"/></svg></span>Item cataloging and guest management</li>
                    <li><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M5 12l4 4 10-10"/></svg></span>One-click financial reconciliation reports</li>
                </ul>
                <button class="sbp-btn sbp-btn-primary" type="button" data-modal-open="dashModal">Explore the Dashboard</button>
            </div>

            <div class="sbp-dash" role="img" aria-label="Administrator dashboard preview for the Spring Gala Silent Auction: total raised 128,450 dollars at 128 percent of goal, 342 active bidders, 215 items, 96,320 dollars collected, and an automated closeout panel showing payment processor connected, receipts auto-sending, and payout scheduled for May 15.">
                <div class="sbp-dash-side" aria-hidden="true">
                    <div class="logo"><img src="images/brand/silentbidpro-logo-dark.png" alt=""></div>
                    <nav>
                        <a class="active"><span class="d"></span>Dashboard</a>
                        <a><span class="d"></span>Items</a>
                        <a><span class="d"></span>Bidders</a>
                        <a><span class="d"></span>Live Activity</a>
                        <a><span class="d"></span>Payments</a>
                        <a><span class="d"></span>Reports</a>
                    </nav>
                </div>
                <div class="sbp-dash-main" aria-hidden="true">
                    <div class="sbp-dash-bar">
                        <span class="sel">Spring Gala Silent Auction <span class="caret">&#9662;</span></span>
                        <span class="sbp-dash-live"><span class="dot"></span>Live</span>
                    </div>
                    <div class="sbp-dash-tiles">
                        <div class="sbp-dash-tile"><span>Total Raised</span><b>$128,450</b><small>128% of $100,000 goal</small></div>
                        <div class="sbp-dash-tile"><span>Active Bidders</span><b>342</b><small>28 today</small></div>
                        <div class="sbp-dash-tile"><span>Total Items</span><b>215</b><small>12 closing soon</small></div>
                        <div class="sbp-dash-tile"><span>Collected Payments</span><b>$96,320</b><small>75% collected</small></div>
                    </div>
                    <div class="sbp-dash-closeout">
                        <div class="head"><h4>Automated Closeout</h4><span>All systems ready</span></div>
                        <div class="sbp-closeout-row">
                            <span class="lab">Payment Processor<span>Cards and wallets ready</span></span>
                            <span class="stat"><span class="d"></span>Connected</span>
                        </div>
                        <div class="sbp-closeout-row">
                            <span class="lab">Receipts<span>Sent on every payment</span></span>
                            <span class="stat"><span class="d"></span>Auto-Sending</span>
                        </div>
                        <div class="sbp-closeout-row">
                            <span class="lab">Scheduled Payout<span>Next transfer</span></span>
                            <span class="stat"><span class="d"></span>May 15</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     SECTION 7 — AFTER THE AUCTION
     ============================================================ -->
<section class="sbp-section" id="resources" aria-labelledby="closeoutTitle">
    <div class="sbp-wrap">
        <div class="sbp-head">
            <p class="sbp-eyebrow">Automated post-event closeout</p>
            <h2 id="closeoutTitle" class="sbp-serif">Zero manual chasing. Complete settlement in minutes.</h2>
            <p>Automate card charges, tax-compliant receipting, item fulfillment, and accounting exports the moment bidding closes.</p>
        </div>
        <div class="sbp-closeout" data-closeout>
            <div class="sbp-closeout-copy">
                <div class="sbp-close-steps" role="tablist" aria-label="Closeout workflow steps">
                    <button class="sbp-close-step" role="tab" id="cctab-0" aria-selected="false" aria-controls="closePanel" tabindex="-1" data-step="0">
                        <span class="n">1</span><span class="lab"><b>Bidding Locks</b><span>Winners auto-charged the second bidding ends</span></span>
                    </button>
                    <button class="sbp-close-step is-active" role="tab" id="cctab-1" aria-selected="true" aria-controls="closePanel" tabindex="0" data-step="1">
                        <span class="n">2</span><span class="lab"><b>Settlement &amp; Receipts</b><span>Balances reconciled and tax receipts sent</span></span>
                    </button>
                    <button class="sbp-close-step" role="tab" id="cctab-2" aria-selected="false" aria-controls="closePanel" tabindex="-1" data-step="2">
                        <span class="n">3</span><span class="lab"><b>Final Deposit &amp; Export</b><span>Funds deposited and books exported</span></span>
                    </button>
                </div>
                <a class="sbp-btn sbp-btn-primary" href="#request-demo" style="margin-top:26px;">See Closeout &amp; Reporting Demo</a>
            </div>

            <div class="sbp-close-card" id="closePanel" role="tabpanel" aria-labelledby="cctab-1" aria-live="polite">
                <div class="sbp-close-cardhead">
                    <b>Spring Gala 2025 - Final Closeout</b>
                    <span class="sbp-close-badge"><span class="d"></span><span data-cc-badge>Settlement Automated</span></span>
                </div>
                <div class="sbp-close-metrics" data-cc-inner>
                    <div class="sbp-close-metric">
                        <span class="lab">Paid Balance</span>
                        <b data-cc-paid>$96,320</b>
                        <span class="sub" data-cc-paidsub>268 card charges processed</span>
                    </div>
                    <div class="sbp-close-metric">
                        <span class="lab">Unpaid Balance</span>
                        <b data-cc-unpaid>$32,130</b>
                        <span class="pill" data-cc-unpaidpill>Auto-SMS reminders active</span>
                    </div>
                    <div class="sbp-close-metric">
                        <span class="lab">Tax Receipts</span>
                        <b data-cc-receipts>268 Sent</b>
                        <span class="sub" data-cc-receiptssub>IRS-compliant PDFs generated</span>
                    </div>
                    <div class="sbp-close-metric">
                        <span class="lab">Item Pickup</span>
                        <b data-cc-pickup>41 Pending</b>
                        <span class="pill" data-cc-pickuppill>QR scan hand-off ready</span>
                    </div>
                </div>
                <div class="sbp-close-rev">
                    <h5>Revenue by Category</h5>
                    <div class="sbp-cat-row"><span>Travel &amp; Vacations</span><span class="sbp-cat-bar"><i style="width:100%"></i></span><span class="val">$44,200</span></div>
                    <div class="sbp-cat-row"><span>VIP Experiences</span><span class="sbp-cat-bar"><i style="width:72%"></i></span><span class="val">$31,800</span></div>
                    <div class="sbp-cat-row"><span>Fine Dining</span><span class="sbp-cat-bar"><i style="width:48%"></i></span><span class="val">$21,150</span></div>
                    <div class="sbp-cat-row"><span>Art &amp; Items</span><span class="sbp-cat-bar"><i style="width:34%"></i></span><span class="val">$15,100</span></div>
                </div>
                <div class="sbp-close-utility">
                    <button type="button" tabindex="-1">Export CSV for Accounting</button>
                    <button type="button" tabindex="-1">Donor Tax Summary</button>
                    <button type="button" tabindex="-1">Sync to QuickBooks</button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     SECTION 8 — FINAL CTA
     ============================================================ -->
<section class="sbp-final" id="request-demo" aria-labelledby="finalTitle">
    <div class="sbp-wrap">
        <div class="sbp-final-card">
            <span class="sbp-final-badge">Elevate your next auction</span>
            <h2 id="finalTitle" class="sbp-serif">Your next auction should feel this organized.</h2>
            <p>Create an unforgettable, branded experience for your guests and run your entire event from one command center.</p>
            <div class="sbp-final-actions">
                <a class="sbp-btn sbp-btn-primary sbp-btn-lg" href="admin.php">Request a Demo</a>
                <a class="sbp-btn sbp-btn-secondary sbp-btn-lg on-dark" href="#pricing">View Pricing</a>
            </div>
            <p class="sbp-final-note"><span class="d"></span>Ready for your next gala &bull; No app store downloads required &bull; Instant setup</p>
        </div>
    </div>
</section>

</main>

<!-- ============================================================
     SECTION 9 — FOOTER
     ============================================================ -->
<footer class="sbp-footer" id="pricing">
    <div class="sbp-wrap">
        <div class="sbp-footer-grid">
            <div class="sbp-footer-brand">
                <img src="images/brand/silentbidpro-logo-black.png" alt="Silent Bid Pro" width="150" height="38">
                <p>The modern operating platform for mission-driven fundraising auctions.</p>
                <span class="sbp-footer-status"><span class="d"></span>All Systems Operational</span>
            </div>
            <div class="sbp-footer-col">
                <h5>Platform</h5>
                <a href="#platform">Overview</a>
                <a href="#for-bidders">Bidder Experience</a>
                <a href="#for-administrators">Command Center</a>
                <a href="#resources">Closeout &amp; Reporting</a>
                <a href="#how-it-works">Live Auction Pulse</a>
                <a href="#pricing">Pricing</a>
            </div>
            <div class="sbp-footer-col">
                <h5>Solutions</h5>
                <a href="#for-organizations">Nonprofits &amp; Foundations</a>
                <a href="#for-organizations">Corporate CSR &amp; Giving</a>
                <a href="#for-organizations">Small Business &amp; Associations</a>
                <a href="#for-organizations">Private Schools &amp; Universities</a>
                <a href="#for-organizations">Healthcare Foundations</a>
            </div>
            <div class="sbp-footer-col">
                <h5>Resources</h5>
                <a href="#resources">Auction Planning Guide</a>
                <a href="#for-bidders">Bidder Quick-Start</a>
                <a href="#request-demo">Help Center</a>
                <a href="#request-demo">Security &amp; Compliance</a>
                <a href="#request-demo">System Status</a>
            </div>
            <div class="sbp-footer-col">
                <h5>Company</h5>
                <a href="#platform">About Us</a>
                <a href="#request-demo">Contact Sales</a>
                <a href="privacy.php">Privacy Policy</a>
                <a href="terms.php">Terms of Service</a>
                <a href="bid.php">Sign In</a>
                <a href="#request-demo">Request a Demo</a>
            </div>
        </div>
        <div class="sbp-footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>. All rights reserved.</span>
            <div class="sbp-social" aria-label="Social links">
                <a href="#" aria-label="Silent Bid Pro on LinkedIn"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4.98 3.5a2.5 2.5 0 11-.02 5 2.5 2.5 0 01.02-5zM3 9h4v12H3zM10 9h3.8v1.7h.05c.53-1 1.83-2 3.75-2C21.4 8.7 22 11.1 22 14.2V21h-4v-6c0-1.4 0-3.2-2-3.2s-2.3 1.5-2.3 3.1V21h-4z"/></svg></a>
                <a href="#" aria-label="Silent Bid Pro on X"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18 3h3l-7 8 8 10h-6l-5-6-5 6H3l8-9L3 3h6l4 5z"/></svg></a>
                <a href="#" aria-label="Silent Bid Pro on Facebook"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14 9h3V6h-3c-2.2 0-4 1.8-4 4v2H7v3h3v6h3v-6h3l1-3h-4v-2c0-.6.4-1 1-1z"/></svg></a>
            </div>
        </div>
    </div>
</footer>

<!-- ============================================================
     MODALS — product walkthroughs
     ============================================================ -->
<div class="sbp-modal" id="liveModal" role="dialog" aria-modal="true" aria-labelledby="liveModalTitle" aria-hidden="true">
    <div class="sbp-modal-scrim" data-modal-close></div>
    <div class="sbp-modal-box">
        <button class="sbp-modal-close" type="button" aria-label="Close" data-modal-close>&times;</button>
        <h3 id="liveModalTitle" class="sbp-serif">Watch a live auction</h3>
        <p>Here is how a bidding night unfolds inside Silent Bid Pro.</p>
        <ol class="sbp-modal-steps">
            <li><span class="n">1</span><span><b>Guests check in</b><span>A quick registration gets bidders into your branded auction.</span></span></li>
            <li><span class="n">2</span><span><b>Bids roll in</b><span>Current bids, bid counts, and closing times update in real time.</span></span></li>
            <li><span class="n">3</span><span><b>The room closes</b><span>Items close on schedule and winners are notified right away.</span></span></li>
        </ol>
        <div class="sbp-modal-actions">
            <a class="sbp-btn sbp-btn-primary" href="items.php">Open the Live Auction</a>
            <button class="sbp-btn sbp-btn-secondary" type="button" data-modal-close>Close</button>
        </div>
    </div>
</div>

<div class="sbp-modal" id="dashModal" role="dialog" aria-modal="true" aria-labelledby="dashModalTitle" aria-hidden="true">
    <div class="sbp-modal-scrim" data-modal-close></div>
    <div class="sbp-modal-box">
        <button class="sbp-modal-close" type="button" aria-label="Close" data-modal-close>&times;</button>
        <h3 id="dashModalTitle" class="sbp-serif">Explore the dashboard</h3>
        <p>The command center where you run the whole event.</p>
        <ol class="sbp-modal-steps">
            <li><span class="n">1</span><span><b>See it all at a glance</b><span>Total raised, active bidders, items, and payments in one view.</span></span></li>
            <li><span class="n">2</span><span><b>Manage as you go</b><span>Items, bidders, teams, and closing times stay in your control.</span></span></li>
            <li><span class="n">3</span><span><b>Close with confidence</b><span>Payments, receipts, and payouts are handled after bidding ends.</span></span></li>
        </ol>
        <div class="sbp-modal-actions">
            <a class="sbp-btn sbp-btn-primary" href="admin.php">Open the Admin Console</a>
            <button class="sbp-btn sbp-btn-secondary" type="button" data-modal-close>Close</button>
        </div>
    </div>
</div>

<script>
// ---- Sticky nav: transparent over hero, solid on scroll ----
(function () {
    var nav = document.getElementById('sbpNav');
    function onScroll() { nav.classList.toggle('is-solid', window.scrollY > 24); }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
})();

// ---- Mobile drawer ----
(function () {
    var drawer = document.getElementById('sbpDrawer');
    var toggle = document.querySelector('[data-drawer-open]');
    function open() { drawer.setAttribute('aria-hidden', 'false'); toggle.setAttribute('aria-expanded', 'true'); document.body.style.overflow = 'hidden'; }
    function close() { drawer.setAttribute('aria-hidden', 'true'); toggle.setAttribute('aria-expanded', 'false'); document.body.style.overflow = ''; }
    toggle.addEventListener('click', open);
    drawer.querySelectorAll('[data-drawer-close]').forEach(function (el) { el.addEventListener('click', close); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();

// ---- Accessible modals with focus handling ----
(function () {
    var lastFocus = null;
    function openModal(id) {
        var m = document.getElementById(id);
        if (!m) return;
        lastFocus = document.activeElement;
        m.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        var f = m.querySelector('a, button');
        if (f) f.focus();
    }
    function closeModal(m) {
        m.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (lastFocus) lastFocus.focus();
    }
    document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
        btn.addEventListener('click', function () { openModal(btn.getAttribute('data-modal-open')); });
    });
    document.querySelectorAll('.sbp-modal').forEach(function (m) {
        m.querySelectorAll('[data-modal-close]').forEach(function (el) { el.addEventListener('click', function () { closeModal(m); }); });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.sbp-modal[aria-hidden="false"]').forEach(closeModal);
    });
})();

// ---- Brand Switcher: re-skins a single preview window per tab ----
(function () {
    var root = document.querySelector('[data-brand-switcher]');
    if (!root) return;
    var THEMES = {
        health:       { org: "Children's Health Foundation", initials: 'CH', title: 'Healing Starts Here',       pct: '142%', amount: '$284,000', brand: '#12395f', brand2: '#0a1c30', img: 'images/items/web/org-health.jpg',       pos: '58% 15%' },
        rescue:       { org: 'Happy Tails Animal Rescue',     initials: 'HT', title: 'Help Today. Hope Forever.',  pct: '118%', amount: '$118,500', brand: '#c2531f', brand2: '#7a3211', img: 'images/items/web/org-rescue.jpg',       pos: 'center 32%' },
        gala:         { org: 'Riverdale University',          initials: 'RU', title: 'Invest in Tomorrow',        pct: '135%', amount: '$540,000', brand: '#7d1f2e', brand2: '#420d16', img: 'images/items/web/org-gala.jpg',         pos: 'center 42%' },
        conservation: { org: 'Nature Forward Conservation',   initials: 'NF', title: 'Protect Our Future',        pct: '124%', amount: '$372,000', brand: '#1f6b4a', brand2: '#0c2c20', img: 'images/items/web/org-conservation.jpg', pos: 'center 40%' }
    };
    var tabs = Array.prototype.slice.call(root.querySelectorAll('.sbp-switch-tab'));
    var preview = root.querySelector('.sbp-switch-preview');
    var inner = root.querySelector('[data-preview-inner]');
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var current = 'health';

    function paint(key) {
        var t = THEMES[key];
        if (!t) return;
        preview.style.setProperty('--brand', t.brand);
        preview.style.setProperty('--brand-2', t.brand2);
        preview.style.setProperty('--img', 'url("../' + t.img + '")');
        preview.style.setProperty('--pos', t.pos);
        root.querySelector('[data-p-initials]').textContent = t.initials;
        root.querySelector('[data-p-org]').textContent = t.org;
        root.querySelector('[data-p-title]').textContent = t.title;
        root.querySelector('[data-p-pct]').textContent = t.pct;
        root.querySelector('[data-p-amount]').textContent = t.amount;
    }

    function select(tab, focusPanel) {
        var key = tab.getAttribute('data-theme');
        if (key === current) return;
        current = key;
        tabs.forEach(function (t) {
            var on = t === tab;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.setAttribute('tabindex', on ? '0' : '-1');
            t.classList.toggle('is-active', on);
        });
        preview.setAttribute('aria-labelledby', tab.id);
        // Crossfade: fade out, swap content mid-fade, fade back in (no layout shift).
        if (reduce) { paint(key); return; }
        inner.classList.add('is-swapping');
        window.setTimeout(function () {
            paint(key);
            inner.classList.remove('is-swapping');
        }, 170);
    }

    tabs.forEach(function (tab, i) {
        tab.addEventListener('click', function () { select(tab); tab.focus(); });
        tab.addEventListener('keydown', function (e) {
            var idx = null;
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') idx = (i + 1) % tabs.length;
            else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') idx = (i - 1 + tabs.length) % tabs.length;
            else if (e.key === 'Home') idx = 0;
            else if (e.key === 'End') idx = tabs.length - 1;
            if (idx === null) return;
            e.preventDefault();
            tabs[idx].focus();
            select(tabs[idx]);
        });
    });
})();

// ---- Bidder Journey stepper: tracker + frameless phone, auto-play + crossfade ----
(function () {
    var root = document.querySelector('[data-stepper]');
    if (!root) return;
    var bell = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M10 21h4"/></svg>';
    var sms = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16v14H4zM4 7l8 5 8-5"/></svg>';
    var apple = '<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M16 2c0 1.3-.5 2.4-1.3 3.2-.9.9-2 1.5-3.1 1.4-.1-1.2.5-2.4 1.2-3.2C13.6 2.6 14.9 2 16 2zm3.5 15.6c-.5 1.2-.8 1.7-1.5 2.7-.9 1.4-2.3 3.1-3.9 3.1-1.5 0-1.8-1-3.8-1-1.9 0-2.3 1-3.7 1-1.6 0-2.8-1.5-3.8-2.9C.6 17.6.4 13.3 2 11c1-1.5 2.6-2.4 4.1-2.4 1.6 0 2.6 1 3.9 1 1.3 0 2-1 3.9-1 1.3 0 2.7.7 3.7 2-3.2 1.8-2.7 6.4.4 7z"/></svg>';
    var check = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12l4 4 10-10"/></svg>';

    var STEPS = [
        {
            num: '01', title: 'Check-In',
            desc: 'Guests join instantly. A one-tap magic link gets them into your branded auction, with no app and no password.',
            bullets: ['SMS magic link', 'No passwords required', 'Instant access'],
            screen:
                '<div class="sbp-app-org"><span class="mk">GE</span>Greenfield Education Gala</div>' +
                '<div class="sbp-app-checkin"><h4 class="sbp-serif">Welcome</h4><p>Join the auction in seconds.</p>' +
                '<button class="sbp-app-btn" type="button" tabindex="-1">' + sms + 'Send One-Tap Login Link</button>' +
                '<div class="sbp-app-or">Or enter phone number</div><div class="sbp-app-field">(555) 019-2831</div></div>'
        },
        {
            num: '02', title: 'Browse & Bid',
            desc: 'Guests explore your catalog and bid in a single tap, with live prices and bid counts updating in real time.',
            bullets: ['Real-time bid updates', 'One-tap quick bid', 'Save favorites to a watchlist'],
            screen:
                '<div class="sbp-app-filters"><span class="on">All</span><span>Travel</span><span>Dining</span><span>Wine</span></div>' +
                '<div class="sbp-app-item"><div class="sbp-app-item-img"><img src="images/items/web/wine.jpg" alt=""></div>' +
                '<span class="sbp-app-tag">Featured Item</span><b class="sbp-app-item-name">Napa Vineyard Weekend Getaway</b>' +
                '<div class="sbp-app-bidrow"><span>Current Bid</span><strong>$650</strong><small>6 bids placed</small></div>' +
                '<button class="sbp-app-btn pill" type="button" tabindex="-1">Quick Bid $700</button></div>'
        },
        {
            num: '03', title: 'Real-Time Alerts',
            desc: 'The moment someone bids higher, guests get an instant alert and can jump back in with a single tap.',
            bullets: ['Push and SMS alerts', 'Re-bid in one tap', 'Never miss an item'],
            screen:
                '<div class="sbp-app-alert">' + bell + '<div><b>You have been outbid</b><span>Napa Vineyard Weekend</span></div></div>' +
                '<div class="sbp-app-outbid"><span class="lab">New Highest Bid</span><strong>$750</strong>' +
                '<button class="sbp-app-btn" type="button" tabindex="-1">Raise Bid to $800</button></div>'
        },
        {
            num: '04', title: 'One-Tap Checkout',
            desc: 'Winners pay in seconds with a mobile wallet or a saved card, and their receipt sends automatically.',
            bullets: ['Apple Pay and Google Pay', 'Saved card on file', 'Automatic emailed receipt'],
            screen:
                '<div class="sbp-app-won"><div class="sbp-app-won-badge">You Won</div>' +
                '<b class="sbp-app-item-name">Napa Vineyard Weekend Getaway</b><div class="sbp-app-price">$800</div>' +
                '<div class="sbp-app-pay"><button class="pay apple" type="button" tabindex="-1">' + apple + 'Pay</button>' +
                '<button class="pay card" type="button" tabindex="-1">Card •••• 4242</button></div>' +
                '<div class="sbp-app-success">' + check + '<div><b>Payment Successful</b><span>Receipt sent to email</span></div></div></div>'
        }
    ];

    var tabs = Array.prototype.slice.call(root.querySelectorAll('.sbp-track-item'));
    var panel = root.querySelector('#bidderPanel');
    var narrInner = root.querySelector('[data-bnarrative]');
    var screen = root.querySelector('[data-bscreen]');
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var current = 0, timer = null, auto = true, swapTimer = null;

    function render(i) {
        var s = STEPS[i];
        narrInner.querySelector('[data-bn-num]').textContent = s.num;
        narrInner.querySelector('[data-bn-title]').textContent = s.title;
        narrInner.querySelector('[data-bn-desc]').textContent = s.desc;
        narrInner.querySelector('[data-bn-bullets]').innerHTML = s.bullets.map(function (b) { return '<li>' + b + '</li>'; }).join('');
        screen.innerHTML = s.screen;
    }

    function select(i, focus) {
        if (i === current) return;
        current = i;
        tabs.forEach(function (t, k) {
            var on = k === i;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.setAttribute('tabindex', on ? '0' : '-1');
            t.classList.toggle('is-active', on);
        });
        panel.setAttribute('aria-labelledby', tabs[i].id);
        if (focus) tabs[i].focus();
        if (reduce) { render(i); return; }
        if (swapTimer) window.clearTimeout(swapTimer);
        narrInner.classList.add('is-swapping');
        screen.classList.add('is-swapping');
        swapTimer = window.setTimeout(function () {
            render(i);
            narrInner.classList.remove('is-swapping');
            screen.classList.remove('is-swapping');
            swapTimer = null;
        }, 180);
    }

    function stopAuto() {
        auto = false;
        if (timer) { window.clearInterval(timer); timer = null; }
    }
    function startAuto() {
        if (reduce || timer) return;
        timer = window.setInterval(function () {
            if (!auto) return;
            select((current + 1) % STEPS.length);
        }, 5000);
    }

    tabs.forEach(function (tab, i) {
        tab.addEventListener('click', function () { stopAuto(); select(i); tab.focus(); });
        tab.addEventListener('keydown', function (e) {
            var idx = null;
            if (e.key === 'ArrowDown' || e.key === 'ArrowRight') idx = (i + 1) % tabs.length;
            else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') idx = (i - 1 + tabs.length) % tabs.length;
            else if (e.key === 'Home') idx = 0;
            else if (e.key === 'End') idx = tabs.length - 1;
            if (idx === null) return;
            e.preventDefault();
            stopAuto();
            select(idx, true);
        });
    });

    // Start auto-play only once the section scrolls into view; stop on any interaction.
    if ('IntersectionObserver' in window && !reduce) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { if (en.isIntersecting) { startAuto(); io.disconnect(); } });
        }, { threshold: 0.4 });
        io.observe(root);
    }
    root.addEventListener('mouseenter', function () { if (auto) stopAuto(); });
})();

// ---- Post-event closeout stepper: 3 steps drive the preview card ----
(function () {
    var root = document.querySelector('[data-closeout]');
    if (!root) return;
    var STEPS = [
        { badge: 'Charging Cards',
          paid: '$71,450', paidsub: '198 of 268 cards charged',
          unpaid: '$56,900', unpaidpill: 'Auto-charge in progress',
          receipts: '198 Sent', receiptssub: 'Generating remaining PDFs',
          pickup: '41 Pending', pickuppill: 'QR scan hand-off ready' },
        { badge: 'Settlement Automated',
          paid: '$96,320', paidsub: '268 card charges processed',
          unpaid: '$32,130', unpaidpill: 'Auto-SMS reminders active',
          receipts: '268 Sent', receiptssub: 'IRS-compliant PDFs generated',
          pickup: '41 Pending', pickuppill: 'QR scan hand-off ready' },
        { badge: 'Payout Complete',
          paid: '$128,450', paidsub: 'All winners settled',
          unpaid: '$0', unpaidpill: 'Fully reconciled',
          receipts: '342 Sent', receiptssub: 'All donors receipted',
          pickup: '0 Pending', pickuppill: 'All items handed off' }
    ];
    var tabs = Array.prototype.slice.call(root.querySelectorAll('.sbp-close-step'));
    var panel = root.querySelector('#closePanel');
    var inner = root.querySelector('[data-cc-inner]');
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var current = 1, swapTimer = null;
    var q = function (a) { return root.querySelector('[data-cc-' + a + ']'); };

    function render(i) {
        var s = STEPS[i];
        q('badge').textContent = s.badge;
        q('paid').textContent = s.paid; q('paidsub').textContent = s.paidsub;
        q('unpaid').textContent = s.unpaid; q('unpaidpill').textContent = s.unpaidpill;
        q('receipts').textContent = s.receipts; q('receiptssub').textContent = s.receiptssub;
        q('pickup').textContent = s.pickup; q('pickuppill').textContent = s.pickuppill;
    }

    function select(i, focus) {
        if (i === current) return;
        current = i;
        tabs.forEach(function (t, k) {
            var on = k === i;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.setAttribute('tabindex', on ? '0' : '-1');
            t.classList.toggle('is-active', on);
        });
        panel.setAttribute('aria-labelledby', tabs[i].id);
        if (focus) tabs[i].focus();
        if (reduce) { render(i); return; }
        if (swapTimer) window.clearTimeout(swapTimer);
        inner.classList.add('is-swapping');
        swapTimer = window.setTimeout(function () {
            render(i);
            inner.classList.remove('is-swapping');
            swapTimer = null;
        }, 170);
    }

    tabs.forEach(function (tab, i) {
        tab.addEventListener('click', function () { select(i); tab.focus(); });
        tab.addEventListener('keydown', function (e) {
            var idx = null;
            if (e.key === 'ArrowDown' || e.key === 'ArrowRight') idx = (i + 1) % tabs.length;
            else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') idx = (i - 1 + tabs.length) % tabs.length;
            else if (e.key === 'Home') idx = 0;
            else if (e.key === 'End') idx = tabs.length - 1;
            if (idx === null) return;
            e.preventDefault();
            select(idx, true);
        });
    });
})();

// ---- Reveal App Store badges only when the listing is live ----
function sbpAppStoreCheck(data) {
    if (data && data.resultCount > 0) {
        document.querySelectorAll('[data-appstore-badge]').forEach(function (el) { el.hidden = false; });
    }
}
(function () {
    var s = document.createElement('script');
    s.src = 'https://itunes.apple.com/lookup?id=6787838881&country=us&callback=sbpAppStoreCheck';
    s.async = true;
    document.body.appendChild(s);
})();
</script>
</body>
</html>
