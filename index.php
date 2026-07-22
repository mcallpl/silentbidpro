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

$page_title = APP_NAME . '™ - Your Auction. Your Brand. More Impact.';
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
            <img src="images/brand/silentbidpro-logo-black.png" alt="Silent Bid Pro" width="192" height="48">
            <sup class="sbp-tm" aria-hidden="true">™</sup>
        </a>
        <nav class="sbp-nav-links" aria-label="Sections">
            <a href="#how-it-works">How it works</a>
            <a href="#for-organizations">For Organizations</a>
            <a href="#for-bidders">For Bidders</a>
            <a href="pricing.php">Pricing</a>
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
        <a href="#how-it-works" data-drawer-close>How it works</a>
        <a href="#for-organizations" data-drawer-close>For Organizations</a>
        <a href="#for-bidders" data-drawer-close>For Bidders</a>
        <a href="pricing.php" data-drawer-close>Pricing</a>
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
                <a class="sbp-btn sbp-btn-primary sbp-btn-lg" href="live-auction.php">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="currentColor" opacity="0.25"/><path d="M10 8l6 4-6 4V8z" fill="currentColor"/></svg>
                    Watch a Live Auction
                </a>
                <a class="sbp-btn sbp-btn-secondary sbp-btn-lg" href="command-center.php">
                    Explore the Dashboard
                </a>
            </div>
            <div class="sbp-hero-props" aria-label="Platform highlights">
                <div class="sbp-hero-prop">
                    <span class="sbp-hero-prop-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18M3 12h18"/></svg></span>
                    <strong>Branded your way</strong>
                    <span>Custom to your mission</span>
                </div>
                <div class="sbp-hero-prop">
                    <span class="sbp-hero-prop-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3"/><path d="M4 20c0-3 2-5 5-5s5 2 5 5M17 11l2 2 3-3"/></svg></span>
                    <strong>Easy for guests</strong>
                    <span>Simple and mobile first</span>
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
                    <div class="sbp-feat-head"><b>Featured Items</b><a href="live-auction.php">View all items</a></div>
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
     HOW IT WORKS — the whole story in three steps
     ============================================================ -->
<section class="sbp-section sbp-how" id="how-it-works" aria-labelledby="howTitle">
    <div class="sbp-wrap">
        <div class="sbp-head">
            <p class="sbp-eyebrow">How it works</p>
            <h2 id="howTitle" class="sbp-serif">From setup to payout in three steps.</h2>
            <p>Everything a modern fundraising auction needs, without the parts your team dreads.</p>
        </div>
        <div class="sbp-how-grid">
            <div class="sbp-how-step">
                <span class="sbp-how-n">1</span>
                <span class="sbp-how-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2s6 6 6 10a6 6 0 01-12 0c0-4 6-10 6-10z"/></svg></span>
                <h3>Brand your auction</h3>
                <p>Set your colors, logo, and cover photo in the Branding Studio. No designer required, and you preview it before you go live.</p>
            </div>
            <div class="sbp-how-step">
                <span class="sbp-how-n">2</span>
                <span class="sbp-how-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2.5"/><path d="M11 18h2"/></svg></span>
                <h3>Guests bid from their phones</h3>
                <p>Guests scan a code or tap a link and start bidding in seconds, right from their phone, anywhere in the room.</p>
            </div>
            <div class="sbp-how-step">
                <span class="sbp-how-n">3</span>
                <span class="sbp-how-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg></span>
                <h3>Winners pay, you get paid</h3>
                <p>Cards charge automatically the moment bidding closes, receipts email themselves, and funds land in your account.</p>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     SECTION 3 — ORGANIZATION PERSONALIZATION
     ============================================================ -->
<section class="sbp-section alt" id="for-organizations" aria-labelledby="orgTitle">
    <div class="sbp-wrap">
        <div class="sbp-head">
            <p class="sbp-eyebrow">Admin &middot; White-label branding control</p>
            <h2 id="orgTitle" class="sbp-serif">Your brand takes center stage.</h2>
            <p>From your admin console, customize colors, typography, logos, and imagery so your auction feels like a natural extension of your organization.</p>
        </div>

        <div class="sbp-switcher" data-brand-switcher>
            <div class="sbp-console">
                <div class="sbp-console-bar">
                    <div class="sbp-console-id">
                        <span class="sbp-console-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                        <svg class="sbp-console-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>
                        <b>Branding Studio</b>
                        <span class="sbp-console-tag">Admin</span>
                    </div>
                    <span class="sbp-console-live"><span class="d"></span>Live preview</span>
                </div>
                <div class="sbp-console-body">
                    <p class="sbp-console-label" id="brandPickLabel">Choose a brand to configure</p>
                    <div class="sbp-switch-tabs" role="tablist" aria-labelledby="brandPickLabel">
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
                                    <div class="sbp-switch-cta">
                                        <button class="sbp-switch-btn" type="button" data-p-btn>Preview &amp; customize</button>
                                        <button class="sbp-switch-live" type="button" data-p-live><span class="d" aria-hidden="true"></span>View live auction</button>
                                    </div>
                                </div>
                            </div>
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
            <p>Guests scan a QR code, browse items, place bids, and check out, all from their phone.</p>
        </div>

        <div class="sbp-stepper" data-stepper>
            <!-- Left: progress tracker + narrative -->
            <div class="sbp-stepper-left">
                <div class="sbp-step-tracker" role="tablist" aria-label="Bidder journey steps" aria-orientation="vertical">
                    <button class="sbp-track-item is-active" role="tab" id="btrack-0" aria-selected="true" aria-controls="bidderPanel" tabindex="0" data-step="0">
                        <span class="sbp-track-dot"></span>
                        <span class="sbp-track-txt"><b>Check-In</b><span>Sign in fast with a texted code</span></span>
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
                        <p data-bn-desc>Guests join in seconds with a sign-in code texted to their phone. No password to remember.</p>
                        <ul class="sbp-narr-bullets" data-bn-bullets>
                            <li>Texted sign-in code</li>
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
                                Text me a sign-in code
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
                <a class="sbp-btn sbp-btn-primary" href="command-center.php">Explore the Dashboard</a>
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
            <p class="sbp-eyebrow">Closeout &amp; reporting</p>
            <h2 id="closeoutTitle" class="sbp-serif">Zero manual chasing. Complete settlement in minutes.</h2>
            <p>Automatically charge winners, email receipts, and export a clean report the moment bidding closes.</p>
        </div>
        <div class="sbp-closeout" data-closeout>
            <div class="sbp-closeout-copy">
                <div class="sbp-close-steps" role="tablist" aria-label="Closeout workflow steps">
                    <button class="sbp-close-step" role="tab" id="cctab-0" aria-selected="false" aria-controls="closePanel" tabindex="-1" data-step="0">
                        <span class="n">1</span><span class="lab"><b>Bidding Locks</b><span>Winners auto-charged the second bidding ends</span></span>
                    </button>
                    <button class="sbp-close-step is-active" role="tab" id="cctab-1" aria-selected="true" aria-controls="closePanel" tabindex="0" data-step="1">
                        <span class="n">2</span><span class="lab"><b>Settlement &amp; Receipts</b><span>Balances reconciled and receipts emailed</span></span>
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
                        <span class="lab">Collected</span>
                        <b data-cc-paid>$96,320</b>
                        <span class="sub" data-cc-paidsub>268 winners charged</span>
                    </div>
                    <div class="sbp-close-metric">
                        <span class="lab">Outstanding</span>
                        <b data-cc-unpaid>$32,130</b>
                        <span class="sub" data-cc-unpaidsub>Auto-charging</span>
                    </div>
                    <div class="sbp-close-metric">
                        <span class="lab">Receipts sent</span>
                        <b data-cc-receipts>268</b>
                        <span class="sub" data-cc-receiptssub>Emailed to donors</span>
                    </div>
                </div>
                <div class="sbp-close-utility">
                    <button type="button" tabindex="-1">Export report</button>
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
                <a class="sbp-btn sbp-btn-primary sbp-btn-lg" href="command-center.php?state=empty">Get Started</a>
                <a class="sbp-btn sbp-btn-secondary sbp-btn-lg" href="pricing.php">View Pricing</a>
            </div>
            <p class="sbp-final-note"><span class="d"></span>Ready for your next gala &bull; Instant setup</p>
        </div>
    </div>
</section>

</main>

<!-- ============================================================
     SECTION 9 — FOOTER
     ============================================================ -->
<footer class="sbp-footer">
    <div class="sbp-wrap">
        <div class="sbp-footer-grid">
            <div class="sbp-footer-brand">
                <img src="images/brand/silentbidpro-logo-black.png" alt="Silent Bid Pro" width="150" height="38">
                <p>The modern operating platform for mission-driven fundraising auctions.</p>
            </div>
            <div class="sbp-footer-col">
                <h5>Product</h5>
                <a href="#how-it-works">How it works</a>
                <a href="#for-organizations">For Organizations</a>
                <a href="#for-bidders">For Bidders</a>
                <a href="#for-administrators">Command Center</a>
                <a href="#resources">Closeout &amp; Reporting</a>
                <a href="pricing.php">Pricing</a>
            </div>
            <div class="sbp-footer-col">
                <h5>Company</h5>
                <a href="#request-demo">Request a Demo</a>
                <a href="#request-demo">Contact Sales</a>
                <a href="bid.php">Sign In</a>
                <a href="privacy.php">Privacy Policy</a>
                <a href="terms.php">Terms of Service</a>
            </div>
        </div>
        <div class="sbp-footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>™. All rights reserved.</span>
        </div>
        <p class="sbp-footer-disclaimer">Organizations, auctions, and dollar figures shown throughout this site are illustrative examples, not live data. Silent Bid Pro™ and its logo are trademarks of their owner. Card payments are processed securely by a third-party payment provider.</p>
    </div>
</footer>

<!-- ============================================================
     MODALS — product walkthroughs
     ============================================================ -->


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

    // "Preview & customize" opens the branded page with the live Branding Studio.
    var previewBtn = root.querySelector('[data-p-btn]');
    if (previewBtn) {
        previewBtn.addEventListener('click', function () {
            window.open('preview.php?brand=' + encodeURIComponent(current), '_blank', 'noopener');
        });
    }
    // "View live auction" opens that same brand's live bidding experience.
    var liveBtn = root.querySelector('[data-p-live]');
    if (liveBtn) {
        liveBtn.addEventListener('click', function () {
            window.open('live-auction.php?brand=' + encodeURIComponent(current), '_blank', 'noopener');
        });
    }

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
            desc: 'Guests join in seconds with a sign-in code texted to their phone. No password to remember.',
            bullets: ['Texted sign-in code', 'No passwords required', 'Instant access'],
            screen:
                '<div class="sbp-app-org"><span class="mk">GE</span>Greenfield Education Gala</div>' +
                '<div class="sbp-app-checkin"><h4 class="sbp-serif">Welcome</h4><p>Join the auction in seconds.</p>' +
                '<button class="sbp-app-btn" type="button" tabindex="-1">' + sms + 'Text me a sign-in code</button>' +
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
        { badge: 'Charging winners',
          paid: '$71,450', paidsub: '198 of 268 charged',
          unpaid: '$56,900', unpaidsub: 'Auto-charging',
          receipts: '198', receiptssub: 'Emailed to donors' },
        { badge: 'Settlement automated',
          paid: '$96,320', paidsub: '268 winners charged',
          unpaid: '$32,130', unpaidsub: 'Auto-charging',
          receipts: '268', receiptssub: 'Emailed to donors' },
        { badge: 'Payout complete',
          paid: '$128,450', paidsub: 'All winners settled',
          unpaid: '$0', unpaidsub: 'Fully reconciled',
          receipts: '342', receiptssub: 'All donors receipted' }
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
        q('unpaid').textContent = s.unpaid; q('unpaidsub').textContent = s.unpaidsub;
        q('receipts').textContent = s.receipts; q('receiptssub').textContent = s.receiptssub;
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
