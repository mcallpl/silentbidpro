<?php
// ============================================================
// SILENT BID PRO — Live Auction (branded browse view)
// The "all items" page of the SAME branded auction site as
// preview.php. Shares the preview design system (css/preview.css,
// .pv-* components, per-brand tokens + font) so the two pages
// read as one product. Presentational only — sample data.
// The real bidding is wired server-side (items.php).
// ============================================================
require_once __DIR__ . '/config.php';

// Per-brand theming — matches preview.php so a brand looks identical across both pages.
$THEMES = [
    'health' => [
        'org' => "Children's Health Foundation", 'initials' => 'CH',
        'title' => 'Annual Healing Starts Here Gala', 'date' => 'May 3 – May 10, 2025',
        'pct' => 142, 'raised' => '284,000', 'goal' => '200,000', 'bidders' => 512,
        'brand' => '#12395F', 'brand2' => '#0A1C30', 'soft' => '#EAF1F8',
        'font' => 'Poppins', 'serif' => false,
        'headline' => 'Healing Starts Here', 'hero' => 'images/items/web/org-health.jpg', 'pos' => '58% 18%',
    ],
    'rescue' => [
        'org' => 'Happy Tails Animal Rescue', 'initials' => 'HT',
        'title' => 'Paws & Hearts Benefit Auction', 'date' => 'June 7 – June 14, 2025',
        'pct' => 118, 'raised' => '118,500', 'goal' => '100,000', 'bidders' => 384,
        'brand' => '#C2531F', 'brand2' => '#7A3211', 'soft' => '#FBEDE4',
        'font' => 'Fraunces', 'serif' => true,
        'headline' => 'Help Today. Hope Forever.', 'hero' => 'images/items/web/org-rescue.jpg', 'pos' => 'center 30%',
    ],
    'gala' => [
        'org' => 'Riverdale University', 'initials' => 'RU',
        'title' => 'Scholarship Gala & Silent Auction', 'date' => 'April 12 – April 19, 2025',
        'pct' => 135, 'raised' => '540,000', 'goal' => '400,000', 'bidders' => 726,
        'brand' => '#7D1F2E', 'brand2' => '#420D16', 'soft' => '#F7E9EB',
        'font' => 'Cormorant Garamond', 'serif' => true,
        'headline' => 'Invest in Tomorrow', 'hero' => 'images/items/web/org-gala.jpg', 'pos' => 'center 42%',
    ],
    'conservation' => [
        'org' => 'Nature Forward Conservation', 'initials' => 'NF',
        'title' => 'Wild & Free Conservation Auction', 'date' => 'September 6 – September 13, 2025',
        'pct' => 124, 'raised' => '372,000', 'goal' => '300,000', 'bidders' => 458,
        'brand' => '#1F6B4A', 'brand2' => '#0C2C20', 'soft' => '#E7F1EB',
        'font' => 'Poppins', 'serif' => false,
        'headline' => 'Protect Our Future', 'hero' => 'images/items/web/org-conservation.jpg', 'pos' => 'center 40%',
    ],
];
$brand = $_GET['brand'] ?? 'health';
if (!isset($THEMES[$brand])) { $brand = 'health'; }
$t = $THEMES[$brand];

// Prev/next brand for the example switcher (wraps around).
$brandOrder = array_keys($THEMES);
$bi = array_search($brand, $brandOrder);
$prevBrand = $brandOrder[($bi - 1 + count($brandOrder)) % count($brandOrder)];
$nextBrand = $brandOrder[($bi + 1) % count($brandOrder)];

$cats = ['All', 'Travel', 'Dining', 'Experience', 'Art', 'Wellness'];

$items = [
    ['name' => 'Napa Vineyard Weekend',   'img' => 'wine',    'bid' => '1,250', 'retail' => '2,800', 'bids' => 12, 'cat' => 'Travel',     'left' => '2h 14m'],
    ['name' => 'Mountain Cabin Retreat',  'img' => 'cabin',   'bid' => '950',   'retail' => '2,200', 'bids' => 8,  'cat' => 'Travel',     'left' => '1h 40m'],
    ['name' => 'VIP Theater Night',       'img' => 'theater', 'bid' => '620',   'retail' => '1,400', 'bids' => 6,  'cat' => 'Experience', 'left' => '28m', 'hot' => true],
    ['name' => 'Private Chef Dinner',     'img' => 'gala',    'bid' => '820',   'retail' => '1,800', 'bids' => 9,  'cat' => 'Dining',     'left' => '3h 02m'],
    ['name' => 'Ceramic Art Collection',  'img' => 'art',     'bid' => '540',   'retail' => '1,200', 'bids' => 7,  'cat' => 'Art',        'left' => '2h 51m'],
    ['name' => 'Spa & Wellness Day',      'img' => 'spa',     'bid' => '475',   'retail' => '950',   'bids' => 5,  'cat' => 'Wellness',   'left' => '4h 10m'],
    ['name' => 'Sommelier Wine Tasting',  'img' => 'wine',    'bid' => '380',   'retail' => '850',   'bids' => 4,  'cat' => 'Dining',     'left' => '5h 22m'],
    ['name' => 'Lakeside Photo Session',  'img' => 'cabin',   'bid' => '290',   'retail' => '650',   'bids' => 3,  'cat' => 'Experience', 'left' => '46m', 'hot' => true],
    ['name' => 'Handcrafted Pottery Set', 'img' => 'art',     'bid' => '210',   'retail' => '480',   'bids' => 2,  'cat' => 'Art',        'left' => '6h 05m'],
];

$fontQuery = str_replace(' ', '+', $t['font']);
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$nextBid = fn($bid) => '$' . number_format((int)str_replace(',', '', $bid) + 25);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $e($t['org']); ?> — <?php echo $e($t['title']); ?></title>
    <meta name="robots" content="noindex">
    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=<?php echo $e($fontQuery); ?>:ital,wght@0,500;0,600;0,700;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/preview.css?v=<?php echo @filemtime(__DIR__ . '/css/preview.css') ?: '1'; ?>">
</head>
<body class="pv"
      style="--brand: <?php echo $e($t['brand']); ?>; --brand-2: <?php echo $e($t['brand2']); ?>; --soft: <?php echo $e($t['soft']); ?>; --brand-head: '<?php echo $e($t['font']); ?>', <?php echo $t['serif'] ? 'serif' : 'sans-serif'; ?>;">

<!-- Demo ribbon -->
<div class="pv-ribbon">
    <a class="pv-ribbon-back" href="index.php">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back to site
    </a>
    <div class="pv-ribbon-nav">
        <a class="pv-ribbon-arrow" href="live-auction.php?brand=<?php echo $e($prevBrand); ?>" aria-label="Previous example"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></a>
        <span class="pv-ribbon-label">Example &middot; <?php echo $e($t['org']); ?></span>
        <a class="pv-ribbon-arrow" href="live-auction.php?brand=<?php echo $e($nextBrand); ?>" aria-label="Next example"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg></a>
    </div>
</div>

<!-- Branded header -->
<header class="pv-nav">
    <div class="pv-wrap">
        <a class="pv-brand" href="preview.php?brand=<?php echo $e($brand); ?>">
            <span class="pv-mark"><?php echo $e($t['initials']); ?></span>
            <span class="pv-org"><?php echo $e($t['org']); ?></span>
        </a>
        <nav class="pv-links" aria-label="Auction navigation">
            <a href="preview.php?brand=<?php echo $e($brand); ?>">Home</a>
            <a href="#items">Items</a>
            <a href="preview.php?brand=<?php echo $e($brand); ?>#donate">Donate</a>
            <a href="preview.php?brand=<?php echo $e($brand); ?>#about">About</a>
        </nav>
        <div class="pv-nav-right">
            <a class="pv-signin" href="bid.php">Sign In</a>
            <a class="pv-btn" href="bid.php">Register to Bid</a>
        </div>
    </div>
</header>

<!-- Branded hero: cover photo + headline -->
<section class="pv-hero">
    <div class="pv-hero-img" role="img" aria-label="<?php echo $e($t['title']); ?>">
        <img src="<?php echo $e($t['hero']); ?>" alt="" style="object-position: <?php echo $e($t['pos']); ?>;">
    </div>
    <div class="pv-wrap pv-hero-inner">
        <div class="pv-hero-copy">
            <span class="pv-kicker pv-kicker-live"><span class="d"></span>Live now</span>
            <h1 class="pv-head"><?php echo $e($t['headline']); ?></h1>
            <p class="pv-event"><?php echo $e($t['title']); ?> &middot; <?php echo $e($t['date']); ?></p>
        </div>
    </div>
</section>

<!-- Goal strip -->
<section class="pv-goalstrip">
    <div class="pv-wrap pv-gs-inner">
        <div class="pv-gs-goal">
            <span class="pv-ring" style="--pct: <?php echo (int)min($t['pct'], 100); ?>;">
                <span><b><?php echo (int)$t['pct']; ?>%</b>of goal</span>
            </span>
            <div class="pv-gs-fig">
                <b>$<?php echo $e($t['raised']); ?></b>
                <span>raised of $<?php echo $e($t['goal']); ?> goal</span>
                <div class="pv-progress"><i style="width: <?php echo (int)min($t['pct'], 100); ?>%;"></i></div>
            </div>
        </div>
        <div class="pv-gs-meta">
            <span><b><?php echo (int)$t['bidders']; ?></b>bidders</span>
            <span><b><?php echo count($items); ?>+</b>items</span>
            <span><b>02:15</b>left</span>
        </div>
        <div class="pv-gs-actions">
            <a class="pv-btn" href="#items">Browse Items</a>
            <a class="pv-btn pv-btn-ghost" href="preview.php?brand=<?php echo $e($brand); ?>#donate">Donate</a>
        </div>
    </div>
</section>

<!-- Toolbar -->
<div class="pv-toolbar">
    <div class="pv-wrap pv-toolbar-inner">
        <label class="pv-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
            <input type="search" placeholder="Search items" aria-label="Search items">
        </label>
        <div class="pv-chips" role="tablist" aria-label="Categories">
            <?php foreach ($cats as $i => $c): ?>
                <button class="pv-chip<?php echo $i === 0 ? ' on' : ''; ?>" type="button"><?php echo $e($c); ?></button>
            <?php endforeach; ?>
        </div>
        <label class="pv-sort">
            Sort
            <select aria-label="Sort items">
                <option>Closing soon</option>
                <option>Most bids</option>
                <option>Highest bid</option>
                <option>Newly added</option>
            </select>
        </label>
    </div>
</div>

<!-- Items grid -->
<section class="pv-items" id="items" style="padding: 44px 0 96px;">
    <div class="pv-wrap">
        <p class="pv-countline"><b><?php echo count($items); ?></b> items open for bidding &middot; <b>12</b> closing within the hour</p>
        <div class="pv-grid">
            <?php foreach ($items as $it): $hot = !empty($it['hot']); ?>
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

<!-- Footer -->
<footer class="pv-footer">
    <div class="pv-wrap pv-footer-inner">
        <div class="pv-brand">
            <span class="pv-mark"><?php echo $e($t['initials']); ?></span>
            <span class="pv-org"><?php echo $e($t['org']); ?></span>
        </div>
        <p class="pv-foot-note"><?php echo $e($t['org']); ?> &middot; <?php echo $e($t['title']); ?></p>
        <a class="pv-powered" href="index.php">Powered by <b><?php echo $e(APP_NAME); ?>™</b></a>
    </div>
</footer>

<script>
// Category chips (cosmetic) + item card watchlist toggle + native share.
(function () {
    var chips = document.querySelectorAll('.pv-chip');
    chips.forEach(function (c) {
        c.addEventListener('click', function () {
            chips.forEach(function (x) { x.classList.remove('on'); });
            c.classList.add('on');
        });
    });
    document.querySelectorAll('.pv-fav').forEach(function (b) {
        b.addEventListener('click', function () { b.classList.toggle('on'); });
    });
    document.querySelectorAll('.pv-share').forEach(function (b) {
        b.addEventListener('click', function () {
            var name = b.getAttribute('data-share') || 'this auction item';
            var data = { title: name, text: 'Bid on "' + name + '" in our live silent auction.', url: location.href };
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
