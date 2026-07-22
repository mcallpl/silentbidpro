<?php
// ============================================================
// SILENT BID PRO — White-label branded auction PREVIEW
// A full, elite example of the auction experience a customer
// can create. Themeable via ?brand=health|rescue|gala|conservation.
// Presentational only — no bidding/payment/auth machinery.
// ============================================================
require_once __DIR__ . '/config.php';

$THEMES = [
    'health' => [
        'org' => "Children's Health Foundation", 'initials' => 'CH',
        'tagline' => 'Every child deserves a healthy start.',
        'headline' => 'Healing Starts Here',
        'event' => 'Annual Healing Starts Here Gala',
        'date' => 'May 3 – May 10, 2025',
        'pct' => 142, 'raised' => '284,000', 'goal' => '200,000', 'guests' => 512,
        'brand' => '#12395F', 'brand2' => '#0A1C30', 'soft' => '#EAF1F8',
        'font' => 'Poppins', 'serif' => false,
        'hero' => 'images/items/web/org-health.jpg', 'pos' => '58% 18%',
    ],
    'rescue' => [
        'org' => 'Happy Tails Animal Rescue', 'initials' => 'HT',
        'tagline' => 'Help today. Hope forever.',
        'headline' => 'Help Today. Hope Forever.',
        'event' => 'Paws & Hearts Benefit Auction',
        'date' => 'June 7 – June 14, 2025',
        'pct' => 118, 'raised' => '118,500', 'goal' => '100,000', 'guests' => 384,
        'brand' => '#C2531F', 'brand2' => '#7A3211', 'soft' => '#FBEDE4',
        'font' => 'Fraunces', 'serif' => true,
        'hero' => 'images/items/web/org-rescue.jpg', 'pos' => 'center 30%',
    ],
    'gala' => [
        'org' => 'Riverdale University', 'initials' => 'RU',
        'tagline' => 'Invest in the next generation of leaders.',
        'headline' => 'Invest in Tomorrow',
        'event' => 'Scholarship Gala & Silent Auction',
        'date' => 'April 12 – April 19, 2025',
        'pct' => 135, 'raised' => '540,000', 'goal' => '400,000', 'guests' => 726,
        'brand' => '#7D1F2E', 'brand2' => '#420D16', 'soft' => '#F7E9EB',
        'font' => 'Cormorant Garamond', 'serif' => true,
        'hero' => 'images/items/web/org-gala.jpg', 'pos' => 'center 42%',
    ],
    'conservation' => [
        'org' => 'Nature Forward Conservation', 'initials' => 'NF',
        'tagline' => 'Protecting wild places for good.',
        'headline' => 'Protect Our Future',
        'event' => 'Wild & Free Conservation Auction',
        'date' => 'September 6 – September 13, 2025',
        'pct' => 124, 'raised' => '372,000', 'goal' => '300,000', 'guests' => 458,
        'brand' => '#1F6B4A', 'brand2' => '#0C2C20', 'soft' => '#E7F1EB',
        'font' => 'Poppins', 'serif' => false,
        'hero' => 'images/items/web/org-conservation.jpg', 'pos' => 'center 40%',
    ],
];

$key = $_GET['brand'] ?? 'health';
if (!isset($THEMES[$key])) { $key = 'health'; }
$t = $THEMES[$key];

// Prev/next brand for the example switcher (wraps around).
$brandOrder = array_keys($THEMES);
$bi = array_search($key, $brandOrder);
$prevBrand = $brandOrder[($bi - 1 + count($brandOrder)) % count($brandOrder)];
$nextBrand = $brandOrder[($bi + 1) % count($brandOrder)];

$ITEMS = [
    ['name' => 'Napa Vineyard Weekend',  'img' => 'wine',    'bid' => '1,250', 'retail' => '2,800', 'bids' => 12, 'cat' => 'Travel',     'left' => '2h 14m'],
    ['name' => 'Mountain Cabin Retreat', 'img' => 'cabin',   'bid' => '950',   'retail' => '2,200', 'bids' => 8,  'cat' => 'Travel',     'left' => '1h 40m'],
    ['name' => 'VIP Theater Night',      'img' => 'theater', 'bid' => '620',   'retail' => '1,400', 'bids' => 6,  'cat' => 'Experience', 'left' => '28m', 'hot' => true],
    ['name' => 'Private Chef Dinner',    'img' => 'gala',    'bid' => '820',   'retail' => '1,800', 'bids' => 9,  'cat' => 'Dining',     'left' => '3h 02m'],
    ['name' => 'Ceramic Art Collection', 'img' => 'art',     'bid' => '540',   'retail' => '1,200', 'bids' => 7,  'cat' => 'Art',        'left' => '2h 51m'],
    ['name' => 'Spa & Wellness Day',     'img' => 'spa',     'bid' => '475',   'retail' => '950',   'bids' => 5,  'cat' => 'Wellness',   'left' => '4h 10m'],
];

$fontQuery = str_replace(' ', '+', $t['font']);
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$nextBid = fn($bid) => '$' . number_format((int)str_replace(',', '', $bid) + 25);
$footNote = 'A branded fundraising auction · © ' . date('Y') . ' ' . $t['org'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $e($t['org']); ?> — <?php echo $e($t['event']); ?></title>
    <meta name="robots" content="noindex">
    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=<?php echo $e($fontQuery); ?>:ital,wght@0,500;0,600;0,700;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/preview.css?v=<?php echo @filemtime(__DIR__ . '/css/preview.css') ?: '1'; ?>">
</head>
<body class="pv"
      style="--brand: <?php echo $e($t['brand']); ?>; --brand-2: <?php echo $e($t['brand2']); ?>; --soft: <?php echo $e($t['soft']); ?>; --brand-head: '<?php echo $e($t['font']); ?>', <?php echo $t['serif'] ? 'serif' : 'sans-serif'; ?>;">

<!-- Demo ribbon: makes clear this is a preview and offers a path back -->
<div class="pv-ribbon">
    <a class="pv-ribbon-back" href="index.php">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back to site
    </a>
    <div class="pv-ribbon-nav">
        <a class="pv-ribbon-arrow" href="preview.php?brand=<?php echo $e($prevBrand); ?>" aria-label="Previous example"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></a>
        <span class="pv-ribbon-label">Example &middot; <?php echo $e($t['org']); ?></span>
        <a class="pv-ribbon-arrow" href="preview.php?brand=<?php echo $e($nextBrand); ?>" aria-label="Next example"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg></a>
    </div>
</div>

<!-- Branded header -->
<header class="pv-nav">
    <div class="pv-wrap">
        <a class="pv-brand" href="#items">
            <span class="pv-mark"><?php echo $e($t['initials']); ?></span>
            <span class="pv-org"><?php echo $e($t['org']); ?></span>
        </a>
        <nav class="pv-links" aria-label="Auction navigation">
            <a href="#items">Home</a>
            <a href="#items">Items</a>
            <a href="#donate">Donate</a>
            <a href="#about">About</a>
        </nav>
        <div class="pv-nav-right">
            <a class="pv-signin" href="bid.php">Sign In</a>
            <a class="pv-btn" href="#items">Register to Bid</a>
        </div>
    </div>
</header>

<!-- Hero: event banner + goal card -->
<section class="pv-hero">
    <div class="pv-hero-img" role="img" aria-label="<?php echo $e($t['event']); ?>">
        <img src="<?php echo $e($t['hero']); ?>" alt="" style="object-position: <?php echo $e($t['pos']); ?>;">
    </div>
    <div class="pv-wrap pv-hero-inner">
        <div class="pv-hero-copy">
            <span class="pv-kicker" data-edit="tagline"><?php echo $e($t['tagline']); ?></span>
            <h1 class="pv-head" data-edit="headline"><?php echo $e($t['headline']); ?></h1>
            <p class="pv-event" data-edit="event"><?php echo $e($t['event']); ?> &middot; <?php echo $e($t['date']); ?></p>
        </div>
    </div>
</section>

<!-- Goal progress: clean center strip beneath the hero -->
<section class="pv-goalstrip">
    <div class="pv-wrap pv-gs-inner">
        <div class="pv-gs-goal">
            <span class="pv-ring" style="--pct: <?php echo (int)min($t['pct'],100); ?>;">
                <span><b><?php echo (int)$t['pct']; ?>%</b>of goal</span>
            </span>
            <div class="pv-gs-fig">
                <b>$<?php echo $e($t['raised']); ?></b>
                <span>raised of $<?php echo $e($t['goal']); ?> goal</span>
                <div class="pv-progress"><i style="width: <?php echo (int)min($t['pct'],100); ?>%;"></i></div>
            </div>
        </div>
        <div class="pv-gs-meta">
            <span><b><?php echo count($ITEMS); ?>+</b>items</span>
            <span><b><?php echo (int)$t['guests']; ?></b>guests</span>
            <span><b>02:15</b>left</span>
        </div>
        <div class="pv-gs-actions">
            <a class="pv-btn" href="live-auction.php?brand=<?php echo $e($key); ?>">Browse Items</a>
            <a class="pv-btn pv-btn-ghost" href="#donate">Donate</a>
        </div>
    </div>
</section>

<!-- Featured items -->
<section class="pv-items" id="items">
    <div class="pv-wrap">
        <div class="pv-section-head">
            <div>
                <p class="pv-eyebrow">Silent Auction</p>
                <h2 class="pv-head-2">Featured items</h2>
            </div>
            <div class="pv-filters">
                <span class="on">All</span><span>Travel</span><span>Dining</span><span>Experience</span><span>Art</span>
            </div>
        </div>
        <div class="pv-grid">
            <?php foreach ($ITEMS as $it): $hot = !empty($it['hot']); ?>
                <article class="pv-card">
                    <div class="pv-card-img">
                        <img src="images/items/web/<?php echo $e($it['img']); ?>.jpg" alt="" loading="lazy">
                        <span class="pv-timebadge<?php echo $hot ? ' hot' : ''; ?>"><span class="d"></span><?php echo $hot ? 'Closing soon' : $e($it['left']); ?></span>
                        <div class="pv-card-icons">
                            <button class="pv-icon-btn pv-fav" type="button" aria-label="Add to watchlist">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.8 4.6a5.5 5.5 0 00-7.8 0L12 5.6l-1-1a5.5 5.5 0 00-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 000-7.8z"/></svg>
                            </button>
                            <button class="pv-icon-btn pv-share" type="button" aria-label="Share this item" data-share="<?php echo $e($it['name']); ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="pv-card-body">
                        <span class="pv-tag"><?php echo $e($it['cat']); ?></span>
                        <h3><?php echo $e($it['name']); ?></h3>
                        <div class="pv-bidrow">
                            <span class="pv-bid"><small>Current bid</small><b>$<?php echo $e($it['bid']); ?></b><span class="pv-retail">Retail $<?php echo $e($it['retail']); ?></span></span>
                            <span class="pv-bids"><?php echo (int)$it['bids']; ?> bids</span>
                        </div>
                        <div class="pv-card-actions">
                            <button class="pv-btn pv-btn-block" type="button">Place Bid</button>
                            <button class="pv-quick" type="button">Bid <?php echo $e($nextBid($it['bid'])); ?></button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Mission / impact -->
<section class="pv-mission" id="about">
    <div class="pv-wrap pv-mission-inner">
        <p class="pv-eyebrow on-brand">Our mission</p>
        <h2 class="pv-mission-head"><?php echo $e($t['tagline']); ?></h2>
        <p class="pv-mission-copy">Every bid supports <?php echo $e($t['org']); ?>. Your generosity funds the work that matters, and every gift is tax-deductible.</p>
        <div class="pv-stats">
            <div><b>$<?php echo $e($t['raised']); ?></b><span>Raised this event</span></div>
            <div><b><?php echo (int)$t['pct']; ?>%</b><span>Of goal reached</span></div>
            <div><b><?php echo (int)$t['guests']; ?></b><span>Generous guests</span></div>
        </div>
        <a class="pv-btn pv-btn-lg" href="#donate" id="donate">Make a Donation</a>
    </div>
</section>

<!-- Footer -->
<footer class="pv-footer">
    <div class="pv-wrap pv-footer-inner">
        <div class="pv-brand">
            <span class="pv-mark"><?php echo $e($t['initials']); ?></span>
            <span class="pv-org"><?php echo $e($t['org']); ?></span>
        </div>
        <p class="pv-foot-note"><?php echo $e($footNote); ?></p>
        <a class="pv-powered" href="index.php">Powered by <b><?php echo $e(APP_NAME); ?>™</b></a>
    </div>
</footer>

<!-- ============================================================
     BRANDING STUDIO — interactive customizer by SilentBidPro
     ============================================================ -->
<button class="pv-studio-fab" type="button" data-studio-open aria-haspopup="dialog" aria-controls="brandingStudio">
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/></svg>
    Customize this page
</button>

<aside class="pv-studio" id="brandingStudio" data-studio role="dialog" aria-modal="false" aria-label="Branding Studio" aria-hidden="true">
    <div class="pv-studio-head">
        <div>
            <b>Branding Studio</b>
            <span>Design your auction, live</span>
        </div>
        <button class="pv-studio-x" type="button" data-studio-close aria-label="Close studio">&times;</button>
    </div>

    <div class="pv-studio-body">
        <section class="pv-studio-sec">
            <h4>Brand color</h4>
            <div class="pv-swatches" data-color-swatches>
                <button type="button" style="--sw:#12395F" data-color="#12395F" aria-label="Navy"></button>
                <button type="button" style="--sw:#C2531F" data-color="#C2531F" aria-label="Terracotta"></button>
                <button type="button" style="--sw:#7D1F2E" data-color="#7D1F2E" aria-label="Crimson"></button>
                <button type="button" style="--sw:#1F6B4A" data-color="#1F6B4A" aria-label="Forest"></button>
                <button type="button" style="--sw:#0F766E" data-color="#0F766E" aria-label="Teal"></button>
                <button type="button" style="--sw:#5B3FA8" data-color="#5B3FA8" aria-label="Violet"></button>
                <button type="button" style="--sw:#B45309" data-color="#B45309" aria-label="Amber"></button>
                <button type="button" style="--sw:#1F2937" data-color="#1F2937" aria-label="Charcoal"></button>
            </div>
            <label class="pv-studio-custom">
                <input type="color" data-color-input value="<?php echo $e($t['brand']); ?>">
                <span>Pick a custom color</span>
            </label>
        </section>

        <section class="pv-studio-sec">
            <h4>Typography</h4>
            <div class="pv-fontlist" data-font-list>
                <button type="button" data-font="Poppins" data-kind="sans" style="font-family:'Poppins',sans-serif">Poppins</button>
                <button type="button" data-font="Plus Jakarta Sans" data-kind="sans" style="font-family:'Plus Jakarta Sans',sans-serif">Jakarta</button>
                <button type="button" data-font="Space Grotesk" data-kind="sans" style="font-family:'Space Grotesk',sans-serif">Grotesk</button>
                <button type="button" data-font="Playfair Display" data-kind="serif" style="font-family:'Playfair Display',serif">Playfair</button>
                <button type="button" data-font="Fraunces" data-kind="serif" style="font-family:'Fraunces',serif">Fraunces</button>
                <button type="button" data-font="Cormorant Garamond" data-kind="serif" style="font-family:'Cormorant Garamond',serif">Cormorant</button>
            </div>
        </section>

        <section class="pv-studio-sec">
            <h4>Logo &amp; identity</h4>
            <label class="pv-studio-field">
                <span>Organization name</span>
                <input type="text" data-org-name value="<?php echo $e($t['org']); ?>" maxlength="40">
            </label>
            <label class="pv-studio-field">
                <span>Logo initials</span>
                <input type="text" data-org-initials value="<?php echo $e($t['initials']); ?>" maxlength="3">
            </label>
            <label class="pv-upload" data-logo-drop>
                <input type="file" accept="image/png,image/svg+xml,image/jpeg,image/webp" data-org-logo>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/></svg>
                <span>Upload your logo <small>PNG, SVG, JPG</small></span>
            </label>
            <button type="button" class="pv-logo-reset" data-logo-reset hidden>Remove logo, use initials</button>
        </section>

        <section class="pv-studio-sec">
            <h4>Footer message</h4>
            <label class="pv-studio-field">
                <span>Shown at the bottom of your page</span>
                <textarea data-foot-note rows="2" maxlength="140"><?php echo $e($footNote); ?></textarea>
            </label>
        </section>

        <section class="pv-studio-sec">
            <h4>Hero imagery</h4>
            <div class="pv-imglist" data-image-list>
                <button type="button" data-img="images/items/web/org-health.jpg" data-pos="58% 18%" aria-label="Children"><img src="images/items/web/org-health.jpg" alt=""></button>
                <button type="button" data-img="images/items/web/org-rescue.jpg" data-pos="center 30%" aria-label="Animal"><img src="images/items/web/org-rescue.jpg" alt=""></button>
                <button type="button" data-img="images/items/web/org-gala.jpg" data-pos="center 42%" aria-label="Campus"><img src="images/items/web/org-gala.jpg" alt=""></button>
                <button type="button" data-img="images/items/web/org-conservation.jpg" data-pos="center 40%" aria-label="Nature"><img src="images/items/web/org-conservation.jpg" alt=""></button>
                <button type="button" data-img="images/items/web/gala.jpg" data-pos="center 55%" aria-label="Gala dinner"><img src="images/items/web/gala.jpg" alt=""></button>
            </div>
        </section>
    </div>

    <div class="pv-studio-foot">
        <button class="pv-studio-done" type="button" data-studio-finish>Done</button>
        <a class="pv-btn" href="index.php#request-demo">Launch my auction</a>
    </div>
</aside>

<script>
// Collapsible goal card: header (ring + chevron) stays; body expands/collapses.
(function () {
    document.querySelectorAll('[data-goal]').forEach(function (card) {
        var btn = card.querySelector('.pv-goal-toggle');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var collapsed = card.classList.toggle('is-collapsed');
            btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        });
    });
})();

// ---- Branding Studio: live customizer (colors, type, identity, imagery) ----
(function () {
    var studio = document.querySelector('[data-studio]');
    if (!studio) return;
    var body = document.body;

    // open / close — opening again lifts the launcher back off the bottom so it tracks you
    document.querySelector('[data-studio-open]').addEventListener('click', function () {
        body.classList.remove('pv-studio-done');
        studio.setAttribute('aria-hidden', 'false'); body.classList.add('pv-studio-on');
        setEditable(true);
    });
    studio.querySelectorAll('[data-studio-close]').forEach(function (el) {
        el.addEventListener('click', function () { studio.setAttribute('aria-hidden', 'true'); body.classList.remove('pv-studio-on'); });
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { studio.setAttribute('aria-hidden', 'true'); body.classList.remove('pv-studio-on'); } });

    // color helpers
    function toRgb(h) { h = h.replace('#', ''); return [parseInt(h.slice(0,2),16), parseInt(h.slice(2,4),16), parseInt(h.slice(4,6),16)]; }
    function toHex(r,g,b) { return '#' + [r,g,b].map(function (x) { return Math.max(0,Math.min(255,Math.round(x))).toString(16).padStart(2,'0'); }).join(''); }
    function darken(hex,f) { var c = toRgb(hex); return toHex(c[0]*f, c[1]*f, c[2]*f); }
    function toWhite(hex,f) { var c = toRgb(hex); return toHex(c[0]+(255-c[0])*f, c[1]+(255-c[1])*f, c[2]+(255-c[2])*f); }

    function applyColor(hex) {
        body.style.setProperty('--brand', hex);
        body.style.setProperty('--brand-2', darken(hex, 0.48));
        body.style.setProperty('--soft', toWhite(hex, 0.90));
        var input = studio.querySelector('[data-color-input]');
        if (input) input.value = hex;
        studio.querySelectorAll('[data-color]').forEach(function (b) {
            b.classList.toggle('on', b.getAttribute('data-color').toLowerCase() === hex.toLowerCase());
        });
    }
    studio.querySelectorAll('[data-color]').forEach(function (b) {
        b.addEventListener('click', function () { applyColor(b.getAttribute('data-color')); });
    });
    studio.querySelector('[data-color-input]').addEventListener('input', function () { applyColor(this.value); });

    // typography (lazy-load Google fonts on demand)
    var loaded = { 'Plus Jakarta Sans': true };
    function applyFont(name, kind) {
        if (!loaded[name]) {
            var l = document.createElement('link'); l.rel = 'stylesheet';
            l.href = 'https://fonts.googleapis.com/css2?family=' + name.replace(/ /g, '+') + ':ital,wght@0,500;0,600;0,700;1,600&display=swap';
            document.head.appendChild(l); loaded[name] = true;
        }
        body.style.setProperty('--brand-head', "'" + name + "', " + (kind === 'serif' ? 'serif' : 'sans-serif'));
        studio.querySelectorAll('[data-font]').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-font') === name); });
    }
    studio.querySelectorAll('[data-font]').forEach(function (b) {
        b.addEventListener('click', function () { applyFont(b.getAttribute('data-font'), b.getAttribute('data-kind')); });
    });

    // identity
    studio.querySelector('[data-org-name]').addEventListener('input', function () {
        var v = this.value || 'Your Organization';
        document.querySelectorAll('.pv-org').forEach(function (el) { el.textContent = v; });
        document.title = v;
    });
    studio.querySelector('[data-org-initials]').addEventListener('input', function () {
        var v = (this.value || 'YO').toUpperCase();
        document.querySelectorAll('.pv-mark').forEach(function (el) { el.textContent = v; });
    });

    // footer message
    var footField = studio.querySelector('[data-foot-note]');
    if (footField) footField.addEventListener('input', function () {
        var note = document.querySelector('.pv-foot-note');
        if (note) note.textContent = this.value;
    });

    // ---- tap-to-edit hero: edit the copy right on the page while the tool is tracking ----
    var editEls = document.querySelectorAll('[data-edit]');
    function setEditable(on) {
        editEls.forEach(function (el) {
            el.contentEditable = on ? 'true' : 'false';
            el.classList.toggle('pv-editable', on);
        });
    }
    editEls.forEach(function (el) {
        // Enter finishes the edit; keep pasted text plain
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); el.blur(); }
        });
        el.addEventListener('paste', function (e) {
            e.preventDefault();
            var text = ((e.clipboardData || window.clipboardData).getData('text') || '').replace(/\s+/g, ' ');
            document.execCommand('insertText', false, text);
        });
        // keep the tagline pill and the mission headline in sync
        if (el.getAttribute('data-edit') === 'tagline') {
            el.addEventListener('input', function () {
                var mh = document.querySelector('.pv-mission-head');
                if (mh) mh.textContent = el.textContent;
            });
        }
    });
    setEditable(!body.classList.contains('pv-studio-done'));

    // imagery
    var heroImg = document.querySelector('.pv-hero-img img');
    studio.querySelectorAll('[data-image-list] [data-img]').forEach(function (b) {
        b.addEventListener('click', function () {
            if (heroImg) { heroImg.src = b.getAttribute('data-img'); heroImg.style.objectPosition = b.getAttribute('data-pos') || 'center'; }
            studio.querySelectorAll('[data-image-list] [data-img]').forEach(function (x) { x.classList.toggle('on', x === b); });
        });
    });

    // logo upload (read client-side, show as the auction's logo — preview only)
    var logoInput = studio.querySelector('[data-org-logo]');
    var logoReset = studio.querySelector('[data-logo-reset]');
    function setLogo(url) {
        document.querySelectorAll('.pv-brand').forEach(function (brand) {
            var img = brand.querySelector('.pv-logo-img');
            if (!img) { img = document.createElement('img'); img.className = 'pv-logo-img'; img.alt = 'Logo'; brand.insertBefore(img, brand.firstChild); }
            img.src = url; brand.classList.add('has-logo');
        });
        if (logoReset) logoReset.hidden = false;
    }
    function clearLogo() {
        document.querySelectorAll('.pv-brand').forEach(function (brand) { brand.classList.remove('has-logo'); });
        if (logoReset) logoReset.hidden = true;
        if (logoInput) logoInput.value = '';
    }
    if (logoInput) logoInput.addEventListener('change', function () {
        var file = this.files && this.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (ev) { setLogo(ev.target.result); };
        reader.readAsDataURL(file);
    });
    if (logoReset) logoReset.addEventListener('click', clearLogo);

    // Done: close the panel and let the launcher settle at the bottom of the page.
    // Reopening it from there (handler above) lifts it back into follow mode.
    studio.querySelector('[data-studio-finish]').addEventListener('click', function () {
        studio.setAttribute('aria-hidden', 'true');
        body.classList.remove('pv-studio-on');
        body.classList.add('pv-studio-done');
        setEditable(false);
    });
})();

// ---- item cards: watchlist toggle + native share (demo) ----
(function () {
    document.querySelectorAll('.pv-fav').forEach(function (b) {
        b.addEventListener('click', function () { b.classList.toggle('on'); });
    });
    document.querySelectorAll('.pv-share').forEach(function (b) {
        b.addEventListener('click', function () {
            var name = b.getAttribute('data-share') || 'this auction item';
            var data = { title: name, text: 'Bid on "' + name + '" in our auction.', url: location.href };
            if (navigator.share) { navigator.share(data).catch(function () {}); }
            else if (navigator.clipboard) {
                navigator.clipboard.writeText(location.href);
                b.classList.add('copied');
                setTimeout(function () { b.classList.remove('copied'); }, 1200);
            }
        });
    });
})();
</script>
</body>
</html>
