<?php
// ============================================================
// SILENT BID PRO — Pricing (marketing page)
// Enterprise-grade, elite pricing presentation. Presentational:
// prices mirror the source of truth in includes/plans.php
// (Seedling free · Pro $99/mo · Enterprise $399/mo). The real
// checkout / billing is wired server-side (billing.php + Stripe).
// ============================================================
require_once __DIR__ . '/config.php';
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);

// Reusable check / dash marks for the comparison grid.
function pr_check() { return '<svg class="pr-yes" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-label="Included"><path d="M5 12l4 4 10-10"/></svg>'; }
function pr_dash()  { return '<span class="pr-no" aria-label="Not included">&ndash;</span>'; }

// Comparison rows: [label, seedling, pro, enterprise]  (true = check, false = dash, string = value)
$rows = [
    ['Active auction events',            '1',   '3',   'Unlimited'],
    ['Live bidding &amp; real-time analytics', true, true, true],
    ['Automated payments &amp; instant receipts', true, true, true],
    ['Item cataloging &amp; guest management', true, true, true],
    ['Automated post-event closeout',    true,  true,  true],
    ['Custom white-label branding',      false, true,  true],
    ['CSV data exports',                 false, true,  true],
    ['Big-screen display mode',          false, true,  true],
    ['Priority support',                 false, true,  true],
    ['Multi-chapter management',         false, false, true],
    ['API access',                       false, false, true],
    ['Single sign-on (SSO)',             false, false, true],
];
$cell = function ($v) {
    if ($v === true)  return pr_check();
    if ($v === false) return pr_dash();
    return '<span class="pr-val">' . $v . '</span>';
};

$faqs = [
    ['Do bidders pay to use Silent Bid Pro™?', 'No. Guests join and bid completely free. Plans are for the organization running the auction, never the bidders.'],
    ['What counts as an active event?', 'An auction that is currently open for bidding. Past and closed events do not count toward your plan limit.'],
    ['Can I change plans later?', 'Yes. Upgrade or downgrade anytime from your billing settings, changes take effect on your next cycle.'],
    ['Is there a contract?', 'No. Paid plans are billed monthly and you can cancel anytime.'],
    ['How are payments handled?', 'Bidder payments are processed securely through our payment partner, and funds are paid out directly to your organization.'],
    ['Do you work with nonprofits?', 'Absolutely. Silent Bid Pro™ is built for mission-driven organizations. Reach out and we will help you find the right plan.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing — <?php echo $e(APP_NAME); ?>™</title>
    <meta name="description" content="Simple, transparent pricing that scales with your mission. Seedling, Pro, and Enterprise plans for fundraising auctions.">
    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/landing.css?v=<?php echo @filemtime(__DIR__ . '/css/landing.css') ?: '1'; ?>">
    <link rel="stylesheet" href="css/pricing.css?v=<?php echo @filemtime(__DIR__ . '/css/pricing.css') ?: '1'; ?>">
</head>
<body class="sbp-landing">

<!-- Header -->
<header class="sbp-nav" aria-label="Primary">
    <div class="sbp-wrap">
        <a class="sbp-nav-logo" href="index.php" aria-label="Silent Bid Pro home">
            <img src="images/brand/silentbidpro-logo-black.png" alt="Silent Bid Pro" width="192" height="48">
            <sup class="sbp-tm" aria-hidden="true">™</sup>
        </a>
        <nav class="sbp-nav-links" aria-label="Sections">
            <a href="index.php#how-it-works">How it works</a>
            <a href="index.php#for-organizations">For Organizations</a>
            <a href="index.php#for-bidders">For Bidders</a>
            <a href="pricing.php" aria-current="page">Pricing</a>
        </nav>
        <div class="sbp-nav-right">
            <a class="sbp-nav-signin" href="command-center.php">Sign In</a>
            <a class="sbp-btn sbp-btn-primary" href="index.php#request-demo">Request a Demo</a>
            <button class="sbp-nav-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="prDrawer" data-drawer-open>
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<!-- Mobile drawer -->
<div class="sbp-drawer" id="prDrawer" role="dialog" aria-modal="true" aria-label="Menu" aria-hidden="true">
    <div class="sbp-drawer-scrim" data-drawer-close></div>
    <div class="sbp-drawer-panel">
        <div class="sbp-drawer-head">
            <img src="images/brand/silentbidpro-logo-dark.png" alt="Silent Bid Pro">
            <button class="sbp-drawer-close" type="button" aria-label="Close menu" data-drawer-close>&times;</button>
        </div>
        <a href="index.php#how-it-works" data-drawer-close>How it works</a>
        <a href="index.php#for-organizations" data-drawer-close>For Organizations</a>
        <a href="index.php#for-bidders" data-drawer-close>For Bidders</a>
        <a href="pricing.php" data-drawer-close>Pricing</a>
        <a href="command-center.php" data-drawer-close>Sign In</a>
        <a class="sbp-btn sbp-btn-primary" href="index.php#request-demo" data-drawer-close>Request a Demo</a>
    </div>
</div>

<main id="sbp-main">

<!-- Pricing hero -->
<section class="pr-hero">
    <div class="sbp-wrap">
        <p class="sbp-eyebrow">Pricing</p>
        <h1 class="sbp-serif">Pricing that scales with your mission.</h1>
        <p class="pr-hero-sub">Start free, upgrade when you grow. Every plan includes live bidding, automated payments, and instant receipts. Your bidders always join free.</p>
    </div>
</section>

<!-- Plans -->
<section class="pr-plans-wrap" aria-label="Plans">
    <div class="sbp-wrap">
        <div class="pr-plans">

            <!-- Seedling -->
            <article class="pr-plan">
                <header class="pr-plan-head">
                    <h2 class="pr-plan-name">Seedling</h2>
                    <p class="pr-plan-for">For your first auction</p>
                </header>
                <div class="pr-price"><span class="amt">$0</span><span class="per">forever</span></div>
                <a class="sbp-btn sbp-btn-secondary sbp-btn-block" href="signup.php">Start for free</a>
                <ul class="pr-feats">
                    <li><?php echo pr_check(); ?>1 active auction event</li>
                    <li><?php echo pr_check(); ?>Live bidding &amp; real-time analytics</li>
                    <li><?php echo pr_check(); ?>Automated payments &amp; receipts</li>
                    <li><?php echo pr_check(); ?>Item cataloging &amp; guest management</li>
                    <li><?php echo pr_check(); ?>Automated post-event closeout</li>
                </ul>
            </article>

            <!-- Pro (highlighted) -->
            <article class="pr-plan pr-plan-pop">
                <span class="pr-badge">Most popular</span>
                <header class="pr-plan-head">
                    <h2 class="pr-plan-name">Pro</h2>
                    <p class="pr-plan-for">For growing programs</p>
                </header>
                <div class="pr-price"><span class="amt">$99</span><span class="per">/ month</span></div>
                <a class="sbp-btn sbp-btn-primary sbp-btn-block" href="signup.php?plan=pro">Start with Pro</a>
                <ul class="pr-feats">
                    <li class="pr-feats-lead">Everything in Seedling, plus</li>
                    <li><?php echo pr_check(); ?>Up to 3 active events</li>
                    <li><?php echo pr_check(); ?>Custom white-label branding</li>
                    <li><?php echo pr_check(); ?>CSV data exports</li>
                    <li><?php echo pr_check(); ?>Big-screen display mode</li>
                    <li><?php echo pr_check(); ?>Priority support</li>
                </ul>
            </article>

            <!-- Enterprise -->
            <article class="pr-plan">
                <header class="pr-plan-head">
                    <h2 class="pr-plan-name">Enterprise</h2>
                    <p class="pr-plan-for">For multi-chapter organizations</p>
                </header>
                <div class="pr-price"><span class="amt">$399</span><span class="per">/ month</span></div>
                <a class="sbp-btn sbp-btn-secondary sbp-btn-block" href="index.php#request-demo">Contact sales</a>
                <ul class="pr-feats">
                    <li class="pr-feats-lead">Everything in Pro, plus</li>
                    <li><?php echo pr_check(); ?>Unlimited active events</li>
                    <li><?php echo pr_check(); ?>Multi-chapter management</li>
                    <li><?php echo pr_check(); ?>API access</li>
                    <li><?php echo pr_check(); ?>Single sign-on (SSO)</li>
                    <li><?php echo pr_check(); ?>Dedicated success manager</li>
                </ul>
            </article>

        </div>
        <p class="pr-reassure"><span class="d"></span>Billed monthly &bull; Cancel anytime &bull; Bidders always join free</p>
    </div>
</section>

<!-- Comparison -->
<section class="pr-compare-wrap" aria-labelledby="prCompareTitle">
    <div class="sbp-wrap">
        <div class="sbp-head">
            <p class="sbp-eyebrow">Compare plans</p>
            <h2 id="prCompareTitle" class="sbp-serif">Every detail, side by side.</h2>
        </div>
        <div class="pr-table-scroll">
            <table class="pr-table">
                <thead>
                    <tr>
                        <th scope="col" class="pr-th-feat">Features</th>
                        <th scope="col">Seedling</th>
                        <th scope="col" class="pr-th-pop">Pro</th>
                        <th scope="col">Enterprise</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <th scope="row"><?php echo $r[0]; ?></th>
                            <td><?php echo $cell($r[1]); ?></td>
                            <td class="pr-td-pop"><?php echo $cell($r[2]); ?></td>
                            <td><?php echo $cell($r[3]); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="pr-table-cta">
                        <th scope="row"><span class="pr-price-inline">Monthly price</span></th>
                        <td><b>$0</b><a class="pr-tlink" href="signup.php">Start free</a></td>
                        <td class="pr-td-pop"><b>$99</b><a class="pr-tlink" href="signup.php?plan=pro">Choose Pro</a></td>
                        <td><b>$399</b><a class="pr-tlink" href="index.php#request-demo">Contact sales</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="pr-faq-wrap" aria-labelledby="prFaqTitle">
    <div class="sbp-wrap">
        <div class="sbp-head">
            <p class="sbp-eyebrow">Questions</p>
            <h2 id="prFaqTitle" class="sbp-serif">Good to know.</h2>
        </div>
        <div class="pr-faq">
            <?php foreach ($faqs as $i => $f): ?>
                <details class="pr-faq-item"<?php echo $i === 0 ? ' open' : ''; ?>>
                    <summary>
                        <span><?php echo $e($f[0]); ?></span>
                        <span class="pr-faq-ic" aria-hidden="true"></span>
                    </summary>
                    <p><?php echo $e($f[1]); ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Final CTA -->
<section class="sbp-final" aria-labelledby="prFinalTitle">
    <div class="sbp-wrap">
        <div class="sbp-final-card">
            <span class="sbp-final-badge">Elevate your next auction</span>
            <h2 id="prFinalTitle" class="sbp-serif">Ready to raise more, with less effort?</h2>
            <p>See the full experience for your organization, then pick the plan that fits. Setup takes minutes.</p>
            <div class="sbp-final-actions">
                <a class="sbp-btn sbp-btn-primary sbp-btn-lg" href="index.php#request-demo">Request a Demo</a>
                <a class="sbp-btn sbp-btn-secondary sbp-btn-lg" href="index.php#for-organizations">See it in action</a>
            </div>
            <p class="sbp-final-note"><span class="d"></span>Bidders always free &bull; Cancel anytime</p>
        </div>
    </div>
</section>

</main>

<!-- Footer -->
<footer class="sbp-footer">
    <div class="sbp-wrap">
        <div class="sbp-footer-grid">
            <div class="sbp-footer-brand">
                <img src="images/brand/silentbidpro-logo-black.png" alt="Silent Bid Pro" width="150" height="38">
                <p>The modern operating platform for mission-driven fundraising auctions.</p>
            </div>
            <div class="sbp-footer-col">
                <h5>Product</h5>
                <a href="index.php#how-it-works">How it works</a>
                <a href="index.php#for-organizations">For Organizations</a>
                <a href="index.php#for-bidders">For Bidders</a>
                <a href="index.php#for-administrators">Command Center</a>
                <a href="index.php#resources">Closeout &amp; Reporting</a>
                <a href="pricing.php">Pricing</a>
            </div>
            <div class="sbp-footer-col">
                <h5>Company</h5>
                <a href="index.php#request-demo">Request a Demo</a>
                <a href="index.php#request-demo">Contact Sales</a>
                <a href="command-center.php">Sign In</a>
                <a href="privacy.php">Privacy Policy</a>
                <a href="terms.php">Terms of Service</a>
            </div>
        </div>
        <div class="sbp-footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> <?php echo $e(APP_NAME); ?>™. All rights reserved.</span>
        </div>
        <p class="sbp-footer-disclaimer">Organizations, auctions, and dollar figures shown throughout this site are illustrative examples, not live data. Silent Bid Pro™ and its logo are trademarks of their owner. Card payments are processed securely by a third-party payment provider.</p>
    </div>
</footer>

<script>
// Mobile drawer (mirrors the landing page behavior).
(function () {
    var drawer = document.getElementById('prDrawer');
    if (!drawer) return;
    var toggle = document.querySelector('[data-drawer-open]');
    function open() { drawer.setAttribute('aria-hidden', 'false'); if (toggle) toggle.setAttribute('aria-expanded', 'true'); document.body.style.overflow = 'hidden'; }
    function close() { drawer.setAttribute('aria-hidden', 'true'); if (toggle) toggle.setAttribute('aria-expanded', 'false'); document.body.style.overflow = ''; }
    if (toggle) toggle.addEventListener('click', open);
    drawer.querySelectorAll('[data-drawer-close]').forEach(function (el) { el.addEventListener('click', close); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>
</body>
</html>
