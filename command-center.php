<?php
// ============================================================
// SILENT BID PRO — Command Center (organizer console)
// The organizer's home base: run the event (Dashboard, Items,
// Bidders, Live Activity, Payments, Reports) AND set it up
// (Branding, Subscription, Settings). Same layout doubles as the
// iOS app (responsive: sidebar -> bottom tabs + sheet on phones).
//
// TWO MODES:
//   LIVE — a signed-in organizer (admin session cookie) sees their
//          real organization, events, bids, and payments.
//   DEMO — everyone else (and ?demo=1) sees the sample-data tour.
//   ?state=empty  -> brand-new-organizer empty state (demo only)
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-helpers.php';
require_once __DIR__ . '/includes/admin-auth-middleware.php'; // getCurrentAdmin() etc. (do NOT also include admin-accounts.php — duplicate function names)
require_once __DIR__ . '/includes/plans.php';

$admin = isset($_GET['demo']) ? false : getCurrentAdmin();
$LIVE  = (bool)$admin;

if (!$LIVE):
// ------------------------------------------------------------
// DEMO MODE — sample data (the public marketing tour)
// ------------------------------------------------------------
$EVENTS = [
    'spring' => [
        'event' => 'Spring Gala Silent Auction', 'org' => 'Greenfield Education Fund', 'initials' => 'GE',
        'raised' => '128,450', 'pct' => 128, 'goal' => '100,000',
        'bidders' => 342, 'today' => 28, 'items' => 215, 'closing' => 12,
        'collected' => '96,320', 'collectedPct' => 75, 'payout' => 'May 15',
        'brand' => '#14532D', 'font' => 'Poppins', 'headline' => 'Give Where It Grows', 'tagline' => 'Every gift plants a future.',
        'hero' => 'images/items/web/org-gala.jpg',
    ],
    'health' => [
        'event' => 'Annual Healing Starts Here Gala', 'org' => "Children's Health Foundation", 'initials' => 'CH',
        'raised' => '284,000', 'pct' => 142, 'goal' => '200,000',
        'bidders' => 512, 'today' => 46, 'items' => 260, 'closing' => 18,
        'collected' => '213,000', 'collectedPct' => 75, 'payout' => 'May 17',
        'brand' => '#12395F', 'font' => 'Poppins', 'headline' => 'Healing Starts Here', 'tagline' => 'Every child deserves a healthy start.',
        'hero' => 'images/items/web/org-health.jpg',
    ],
    'rescue' => [
        'event' => 'Paws & Hearts Benefit Auction', 'org' => 'Happy Tails Animal Rescue', 'initials' => 'HT',
        'raised' => '118,500', 'pct' => 118, 'goal' => '100,000',
        'bidders' => 384, 'today' => 31, 'items' => 180, 'closing' => 9,
        'collected' => '88,900', 'collectedPct' => 75, 'payout' => 'Jun 21',
        'brand' => '#C2531F', 'font' => 'Fraunces', 'headline' => 'Help Today. Hope Forever.', 'tagline' => 'Help today. Hope forever.',
        'hero' => 'images/items/web/org-rescue.jpg',
    ],
];
$key = $_GET['brand'] ?? 'spring';
if (!isset($EVENTS[$key])) { $key = 'spring'; }
$ev = $EVENTS[$key];
$isEmpty = (($_GET['state'] ?? '') === 'empty');

// ---- Sample data ----
$items = [
    ['name' => 'Napa Vineyard Weekend',   'cat' => 'Travel',     'bid' => '1,250', 'bids' => 12, 'left' => '2h 14m', 'status' => 'live'],
    ['name' => 'Mountain Cabin Retreat',  'cat' => 'Travel',     'bid' => '950',   'bids' => 8,  'left' => '1h 40m', 'status' => 'live'],
    ['name' => 'VIP Theater Night',       'cat' => 'Experience', 'bid' => '620',   'bids' => 6,  'left' => '28m',    'status' => 'closing'],
    ['name' => 'Private Chef Dinner',     'cat' => 'Dining',     'bid' => '820',   'bids' => 9,  'left' => '3h 02m', 'status' => 'live'],
    ['name' => 'Ceramic Art Collection',  'cat' => 'Art',        'bid' => '540',   'bids' => 7,  'left' => '2h 51m', 'status' => 'live'],
    ['name' => 'Spa & Wellness Day',      'cat' => 'Wellness',   'bid' => '475',   'bids' => 5,  'left' => '4h 10m', 'status' => 'live'],
];
$bidders = [
    ['paddle' => '204', 'name' => 'Marcus Lee',    'items' => 5, 'total' => '3,120', 'status' => 'Leading', 'tone' => 'ok'],
    ['paddle' => '199', 'name' => 'Sofia Reyes',   'items' => 6, 'total' => '4,050', 'status' => 'Leading', 'tone' => 'ok'],
    ['paddle' => '233', 'name' => 'The Callahans', 'items' => 4, 'total' => '2,760', 'status' => 'Active',  'tone' => 'ok'],
    ['paddle' => '187', 'name' => 'Priya Nair',    'items' => 3, 'total' => '1,940', 'status' => 'Active',  'tone' => 'ok'],
    ['paddle' => '210', 'name' => 'James Park',    'items' => 1, 'total' => '475',   'status' => 'Outbid',  'tone' => 'warn'],
];
$activity = [
    ['t' => '2m ago',  'who' => 'Marcus Lee',    'amt' => '1,250', 'item' => 'Napa Vineyard Weekend'],
    ['t' => '4m ago',  'who' => 'Priya Nair',    'amt' => '620',   'item' => 'VIP Theater Night'],
    ['t' => '6m ago',  'who' => 'Sofia Reyes',   'amt' => '820',   'item' => 'Private Chef Dinner'],
    ['t' => '9m ago',  'who' => 'The Callahans', 'amt' => '540',   'item' => 'Ceramic Art Collection'],
    ['t' => '12m ago', 'who' => 'Elena Volkov',  'amt' => '950',   'item' => 'Mountain Cabin Retreat'],
];
$payments = [
    ['who' => 'Marcus Lee',    'method' => 'Visa •• 4242',       'amt' => '1,250', 'status' => 'Paid',    'tone' => 'ok'],
    ['who' => 'Priya Nair',    'method' => 'Apple Pay',          'amt' => '620',   'status' => 'Paid',    'tone' => 'ok'],
    ['who' => 'The Callahans', 'method' => 'Visa •• 3391',       'amt' => '2,760', 'status' => 'Paid',    'tone' => 'ok'],
    ['who' => 'Sofia Reyes',   'method' => 'Visa •• 1174',       'amt' => '4,050', 'status' => 'Pending', 'tone' => 'warn'],
];
$reports = [
    ['name' => 'Financial Reconciliation', 'desc' => 'Every bid, payment, and payout in one ledger.'],
    ['name' => 'Bidder Summary',           'desc' => 'Paddle numbers, contact info, and totals.'],
    ['name' => 'Item Performance',         'desc' => 'Winning bids vs. retail value, by category.'],
    ['name' => 'Tax Receipts',             'desc' => 'Donor receipts with fair-market breakdowns.'],
];
$plans = [
    'seedling'   => ['label' => 'Seedling',   'price' => '$0',   'per' => 'forever', 'feats' => ['1 active event', 'Live bidding & payments', 'Item & guest management']],
    'pro'        => ['label' => 'Pro',        'price' => '$99',  'per' => '/mo',     'feats' => ['3 active events', 'Custom branding', 'CSV exports', 'Big-screen mode', 'Priority support']],
    'enterprise' => ['label' => 'Enterprise', 'price' => '$399', 'per' => '/mo',     'feats' => ['Unlimited events', 'Multi-chapter', 'API access', 'SSO', 'Dedicated success manager']],
];
$currentPlan = 'pro';
$team = [
    ['name' => 'Chip McAllister', 'email' => 'chip@greenfieldfund.org', 'role' => 'Owner'],
    ['name' => 'Kim McAllister',  'email' => 'kim@greenfieldfund.org',  'role' => 'Manager'],
    ['name' => 'Dana White',      'email' => 'dana@greenfieldfund.org', 'role' => 'Viewer'],
];
$orgEvents = []; $selEventId = 0; $endEpoch = null; $goalSet = true; $orgId = 0;
$sparkVals = [6, 11, 18, 24, 30, 38, 49, 60, 72, 88, 110, 142]; $sparkCap = 'Raised over time';
$attMissingPhotos = 3;

else:
// ------------------------------------------------------------
// LIVE MODE — the signed-in organizer's real data
// ------------------------------------------------------------
$orgs = getAdminAccessibleOrganizations($admin['id']) ?: [];
$orgId = (int)($orgs[0]['id'] ?? 0);
if (!$orgId) { $orgId = (int)($admin['organization_id'] ?? 0); }
// Super admins may steer to any organization (?org=); organizers stay pinned
// to their own — the requested id must be in their accessible list.
$reqOrg = (int)($_GET['org'] ?? 0);
if ($reqOrg && $reqOrg !== $orgId) {
    foreach ($orgs as $o) {
        if ((int)$o['id'] === $reqOrg) { $orgId = $reqOrg; break; }
    }
}
$orgRow = $orgId ? dbGetRow("SELECT id, name, slug, contact_email, brand_primary, brand_accent, logo_url FROM organizations WHERE id = ?", [$orgId]) : null;
if (!$orgRow && !empty($admin['is_super_admin'])) {
    $orgRow = dbGetRow("SELECT id, name, slug, contact_email, brand_primary, brand_accent, logo_url FROM organizations ORDER BY id LIMIT 1");
    $orgId = (int)($orgRow['id'] ?? 0);
}
$orgName = $orgRow['name'] ?? 'Your Organization';

// Events are scoped by ORGANIZATION (the organizer owns the whole org; a fresh
// signup has no admin_events rows, so getAdminAccessibleEvents would be empty).
$orgEvents = $orgId ? (dbGetAll(
    "SELECT id, name, slug, status, event_date, auction_start_time, auction_end_time, payment_mode
     FROM events WHERE organization_id = ? ORDER BY created_at DESC", [$orgId]) ?: []) : [];
$isEmpty = empty($orgEvents);

$selEventId = (int)($_GET['event'] ?? 0);
$sel = null;
foreach ($orgEvents as $oe) { if ((int)$oe['id'] === $selEventId) { $sel = $oe; break; } }
if (!$sel && $orgEvents) { $sel = $orgEvents[0]; }
$selEventId = $sel ? (int)$sel['id'] : 0;

// Fundraising goal (migration 012; guarded so pre-migration prod never breaks).
$goalAmt = null;
if ($sel && dbColumnExists('events', 'fundraising_goal')) {
    $goalAmt = dbGetValue("SELECT fundraising_goal FROM events WHERE id = ?", [$selEventId]);
    $goalAmt = $goalAmt !== null ? (float)$goalAmt : null;
}
$goalSet = $goalAmt !== null && $goalAmt > 0;

// ---- Dashboard stats for the selected event ----
$raisedN = $selEventId ? (float)dbGetValue("SELECT COALESCE(SUM(current_high_bid),0) FROM items WHERE event_id = ? AND current_high_bid > 0", [$selEventId]) : 0.0;
$biddersN = $selEventId ? (int)dbGetValue("SELECT COUNT(DISTINCT b.user_id) FROM bids b JOIN items i ON i.id = b.item_id WHERE i.event_id = ?", [$selEventId]) : 0;
$todayN = $selEventId ? (int)dbGetValue("SELECT COUNT(*) FROM users WHERE event_id = ? AND created_at >= CURDATE()", [$selEventId]) : 0;
$itemsN = $selEventId ? (int)dbGetValue("SELECT COUNT(*) FROM items WHERE event_id = ?", [$selEventId]) : 0;
$closingN = $selEventId ? (int)dbGetValue(
    "SELECT COUNT(*) FROM items WHERE event_id = ? AND is_closed = 0
     AND COALESCE(close_time_override, auction_end_time) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 1 HOUR)", [$selEventId]) : 0;
$collectedN = $selEventId ? (float)dbGetValue(
    "SELECT COALESCE(SUM(t.amount),0) FROM transactions t JOIN items i ON i.id = t.item_id
     WHERE i.event_id = ? AND t.status = 'paid'", [$selEventId]) : 0.0;
$attMissingPhotos = $selEventId ? (int)dbGetValue(
    "SELECT COUNT(*) FROM items WHERE event_id = ? AND (image_url IS NULL OR image_url = '')", [$selEventId]) : 0;

$pctN = $goalSet ? (int)round($raisedN / $goalAmt * 100) : null;
$collectedPctN = $raisedN > 0 ? (int)round($collectedN / $raisedN * 100) : 0;

// ---- Lists ----
$items = [];
foreach (($selEventId ? (dbGetAll(
    "SELECT i.title, i.current_high_bid, i.starting_bid, i.is_closed,
            COALESCE(i.close_time_override, i.auction_end_time) AS closes_at,
            COALESCE(c.name, 'General') AS cat, COUNT(b.id) AS bid_count
     FROM items i
     LEFT JOIN categories c ON c.id = i.category_id
     LEFT JOIN bids b ON b.item_id = i.id
     WHERE i.event_id = ?
     GROUP BY i.id, i.title, i.current_high_bid, i.starting_bid, i.is_closed, closes_at, cat
     ORDER BY closes_at ASC", [$selEventId]) ?: []) : []) as $r) {
    $closed = (int)$r['is_closed'] === 1 || strtotime((string)$r['closes_at']) <= time();
    $soon = !$closed && strtotime((string)$r['closes_at']) - time() <= 3600;
    $items[] = [
        'name' => $r['title'], 'cat' => $r['cat'],
        'bid' => number_format((float)($r['current_high_bid'] ?: $r['starting_bid'])),
        'bids' => (int)$r['bid_count'],
        'left' => $closed ? 'Closed' : cc_timeleft(strtotime((string)$r['closes_at'])),
        'status' => $closed ? 'closed' : ($soon ? 'closing' : 'live'),
    ];
}

$bidders = [];
foreach (($selEventId ? (dbGetAll(
    "SELECT u.id, COALESCE(NULLIF(u.full_name,''), u.phone_number) AS name,
            COUNT(DISTINCT b.item_id) AS items_on,
            (SELECT COALESCE(SUM(i2.current_high_bid),0) FROM items i2 WHERE i2.event_id = ? AND i2.current_high_bidder_id = u.id) AS leading_total,
            (SELECT COUNT(*) FROM items i3 WHERE i3.event_id = ? AND i3.current_high_bidder_id = u.id) AS leading_count
     FROM users u
     JOIN bids b ON b.user_id = u.id
     JOIN items i ON i.id = b.item_id AND i.event_id = ?
     GROUP BY u.id, name
     ORDER BY leading_total DESC, items_on DESC
     LIMIT 25", [$selEventId, $selEventId, $selEventId]) ?: []) : []) as $r) {
    $leads = (int)$r['leading_count'] > 0;
    $bidders[] = [
        'paddle' => (string)(int)$r['id'], 'name' => $r['name'],
        'items' => (int)$r['items_on'], 'total' => number_format((float)$r['leading_total']),
        'status' => $leads ? 'Leading' : 'Outbid', 'tone' => $leads ? 'ok' : 'warn',
    ];
}

$activity = [];
foreach (($selEventId ? (dbGetAll(
    "SELECT b.bid_amount, b.created_at, i.title, COALESCE(NULLIF(u.full_name,''), u.phone_number) AS who
     FROM bids b JOIN items i ON i.id = b.item_id JOIN users u ON u.id = b.user_id
     WHERE i.event_id = ? ORDER BY b.created_at DESC LIMIT 8", [$selEventId]) ?: []) : []) as $r) {
    $activity[] = ['t' => cc_ago(strtotime((string)$r['created_at'])), 'who' => $r['who'],
                   'amt' => number_format((float)$r['bid_amount']), 'item' => $r['title']];
}

$payments = [];
foreach (($selEventId ? (dbGetAll(
    "SELECT t.amount, t.status, COALESCE(NULLIF(u.full_name,''), u.phone_number) AS who
     FROM transactions t JOIN items i ON i.id = t.item_id JOIN users u ON u.id = t.user_id
     WHERE i.event_id = ? AND t.status IN ('paid','pending','failed')
     ORDER BY t.created_at DESC LIMIT 12", [$selEventId]) ?: []) : []) as $r) {
    $map = ['paid' => ['Paid', 'ok'], 'pending' => ['Pending', 'warn'], 'failed' => ['Failed', 'warn']];
    [$lab, $tone] = $map[$r['status']] ?? ['Pending', 'warn'];
    $payments[] = ['who' => $r['who'], 'method' => 'Card &middot; Stripe', 'amt' => number_format((float)$r['amount']), 'status' => $lab, 'tone' => $tone];
}

// Sparkline: cumulative bid count per day (honest — we don't historize raised-$).
$sparkVals = [0, 0]; $sparkCap = 'Bids over time';
if ($selEventId) {
    $rows = dbGetAll(
        "SELECT DATE(b.created_at) d, COUNT(*) c FROM bids b JOIN items i ON i.id = b.item_id
         WHERE i.event_id = ? GROUP BY DATE(b.created_at) ORDER BY d ASC", [$selEventId]) ?: [];
    if ($rows) {
        $cum = 0; $sparkVals = [];
        foreach ($rows as $r) { $cum += (int)$r['c']; $sparkVals[] = $cum; }
        if (count($sparkVals) === 1) { array_unshift($sparkVals, 0); }
        $sparkVals = array_slice($sparkVals, -12);
    }
}

$reports = [
    ['name' => 'Financial Reconciliation', 'desc' => 'Every bid, payment, and payout in one ledger.'],
    ['name' => 'Bidder Summary',           'desc' => 'Paddle numbers, contact info, and totals.'],
    ['name' => 'Item Performance',         'desc' => 'Winning bids vs. retail value, by category.'],
    ['name' => 'Tax Receipts',             'desc' => 'Donor receipts with fair-market breakdowns.'],
];

// Plans straight from the server-side catalog (single source of truth).
$featCopy = [
    'free'       => ['1 active event', 'Live bidding & payments', 'Item & guest management'],
    'pro'        => ['3 active events', 'Custom branding', 'CSV exports', 'Big-screen mode', 'Priority support'],
    'enterprise' => ['Unlimited events', 'Multi-chapter', 'API access', 'SSO', 'Dedicated success manager'],
];
$plans = [];
foreach (planCatalog() as $pk => $pc) {
    $plans[$pk] = [
        'label' => $pc['label'],
        'price' => '$' . number_format($pc['price_monthly_cents'] / 100),
        'per'   => $pc['price_monthly_cents'] ? '/mo' : 'forever',
        'feats' => $featCopy[$pk] ?? [],
    ];
}
$currentPlan = getOrgPlan($orgId);

$team = $orgId ? (dbGetAll(
    "SELECT COALESCE(NULLIF(a.full_name,''), a.username) AS name, a.email, ao.role
     FROM admin_organizations ao JOIN admin_accounts a ON a.id = ao.admin_id
     WHERE ao.organization_id = ? ORDER BY FIELD(ao.role,'manager','viewer'), ao.id", [$orgId]) ?: []) : [];
if (!$team) { $team = [['name' => $admin['full_name'] ?: $admin['username'], 'email' => $admin['email'] ?? '', 'role' => 'manager']]; }
foreach ($team as &$tm) { $tm['role'] = ucfirst((string)$tm['role']); } unset($tm);

$endEpoch = null;
if ($sel && !empty($sel['auction_end_time'])) {
    $t = strtotime((string)$sel['auction_end_time']);
    if ($t !== false) { $endEpoch = max(0, $t); }
}

// The same $ev shape the template renders in demo mode.
$initials = '';
foreach (preg_split('/\s+/', trim($orgName)) as $w) { if ($w !== '') { $initials .= mb_strtoupper(mb_substr($w, 0, 1)); } if (mb_strlen($initials) >= 2) break; }
$ev = [
    'event'    => $sel['name'] ?? 'Your first auction',
    'org'      => $orgName,
    'initials' => $initials ?: 'SB',
    'raised'   => number_format($raisedN),
    'pct'      => $pctN,
    'goal'     => $goalSet ? number_format($goalAmt) : null,
    'bidders'  => $biddersN,
    'today'    => $todayN,
    'items'    => $itemsN,
    'closing'  => $closingN,
    'collected' => number_format($collectedN),
    'collectedPct' => $collectedPctN,
    'payout'   => 'Via Stripe',
    'brand'    => $orgRow['brand_primary'] ?? '#12395F',
    'font'     => 'Poppins',
    'headline' => $sel['name'] ?? $orgName,
    'tagline'  => $orgName,
    'hero'     => 'images/items/web/org-gala.jpg',
];
$key = 'live';
endif;

// ---- Shared helpers (both modes) ----
function cc_timeleft($ts) {
    $s = max(0, $ts - time());
    if ($s >= 86400) { return floor($s / 86400) . 'd ' . floor(($s % 86400) / 3600) . 'h'; }
    if ($s >= 3600)  { return floor($s / 3600) . 'h ' . str_pad((string)floor(($s % 3600) / 60), 2, '0', STR_PAD_LEFT) . 'm'; }
    return max(1, floor($s / 60)) . 'm';
}
function cc_ago($ts) {
    $s = max(0, time() - $ts);
    if ($s < 60) { return 'just now'; }
    if ($s < 3600) { return floor($s / 60) . 'm ago'; }
    if ($s < 86400) { return floor($s / 3600) . 'h ago'; }
    return floor($s / 86400) . 'd ago';
}

$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$ownerName = $LIVE ? (($admin['full_name'] ?? '') !== '' ? $admin['full_name'] : $admin['username']) : ($team[0]['name'] ?? 'there');
$ownerFirst = explode(' ', trim($ownerName))[0] ?: 'there';
$bdark = function ($hex) { $hex = ltrim($hex, '#'); if (strlen($hex) !== 6) { return '#0a1a2f'; } return sprintf('#%02x%02x%02x', (int)(hexdec(substr($hex, 0, 2)) * 0.5), (int)(hexdec(substr($hex, 2, 2)) * 0.5), (int)(hexdec(substr($hex, 4, 2)) * 0.5)); };
$navRun = ['dashboard' => 'Dashboard', 'items' => 'Items', 'bidders' => 'Bidders', 'activity' => 'Live Activity', 'payments' => 'Payments', 'reports' => 'Reports'];
$navSet = ['branding' => 'Branding', 'subscription' => 'Subscription', 'settings' => 'Settings'];
$fontQuery = str_replace(' ', '+', $ev['font']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $e($ev['event']); ?> — Command Center</title>
    <meta name="robots" content="noindex">
    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/command-center.css?v=<?php echo @filemtime(__DIR__ . '/css/command-center.css') ?: '1'; ?>">
</head>
<body class="cc">

<!-- Sidebar (desktop) -->
<aside class="cc-side" id="ccSide">
    <a class="cc-logo" href="index.php" aria-label="Silent Bid Pro home">
        <img src="images/brand/silentbidpro-logo-dark.png" alt="Silent Bid Pro">
        <sup class="cc-tm" aria-hidden="true">™</sup>
    </a>
    <nav class="cc-nav" aria-label="Command center">
        <p class="cc-nav-label">Run event</p>
        <?php $first = true; foreach ($navRun as $id => $label): ?>
            <button class="cc-nav-item<?php echo $first ? ' active' : ''; ?>" type="button" data-panel="<?php echo $e($id); ?>">
                <span class="ic" aria-hidden="true"><?php echo cc_icon($id); ?></span><?php echo $e($label); ?>
            </button>
        <?php $first = false; endforeach; ?>
        <p class="cc-nav-label">Set up</p>
        <?php foreach ($navSet as $id => $label): ?>
            <button class="cc-nav-item" type="button" data-panel="<?php echo $e($id); ?>">
                <span class="ic" aria-hidden="true"><?php echo cc_icon($id); ?></span><?php echo $e($label); ?>
            </button>
        <?php endforeach; ?>
    </nav>
    <div class="cc-side-foot">
        <a class="cc-back" href="index.php">&larr; Back to site</a>
        <?php if ($LIVE): ?>
            <a class="cc-back" href="#" data-signout>Sign out</a>
        <?php else: ?>
            <a class="cc-back" href="signup.php?mode=login">Sign in</a>
        <?php endif; ?>
        <p class="cc-side-org"><?php echo $e($ev['org']); ?></p>
    </div>
</aside>
<div class="cc-scrim" data-menu-close aria-hidden="true"></div>

<!-- Main -->
<div class="cc-main">
    <header class="cc-top">
        <div class="cc-top-left">
            <button class="cc-burger" type="button" data-menu-open aria-label="Open menu" aria-controls="ccSide">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <span class="cc-logo-m" aria-hidden="true"><img src="images/brand/silentbidpro-logo-dark.png" alt=""></span>
            <?php if ($LIVE && !empty($admin['is_super_admin']) && count($orgs) > 1): ?>
            <label class="cc-select">
                <select aria-label="Select organization" onchange="if(this.value){location.href='command-center.php?org='+this.value;}">
                    <?php foreach ($orgs as $o): ?>
                        <option value="<?php echo (int)$o['id']; ?>"<?php echo (int)$o['id'] === $orgId ? ' selected' : ''; ?>><?php echo $e($o['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
            <label class="cc-select">
                <?php if ($LIVE): ?>
                <select aria-label="Select auction" onchange="if(this.value){location.href='command-center.php?org=<?php echo (int)$orgId; ?>&event='+this.value;}">
                    <?php if ($isEmpty): ?><option value="">No auctions yet</option><?php endif; ?>
                    <?php foreach ($orgEvents as $oe): ?>
                        <option value="<?php echo (int)$oe['id']; ?>"<?php echo (int)$oe['id'] === $selEventId ? ' selected' : ''; ?>><?php echo $e($oe['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <select aria-label="Select auction" onchange="if(this.value){location.href='command-center.php?demo=1&brand='+this.value;}">
                    <?php foreach ($EVENTS as $k => $opt): ?>
                        <option value="<?php echo $e($k); ?>"<?php echo $k === $key ? ' selected' : ''; ?>><?php echo $e($opt['event']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </label>
        </div>
        <div class="cc-top-right">
            <?php if ($LIVE): ?>
                <?php if ($sel && ($sel['status'] ?? '') === 'open'): ?><span class="cc-live"><span class="dot"></span>Open</span><?php elseif ($sel): ?><span class="cc-demo"><?php echo $e(ucfirst((string)$sel['status'])); ?></span><?php endif; ?>
                <a class="cc-statelink" href="#" data-signout>Sign out</a>
                <span class="cc-admin"><span class="cc-avatar"><?php echo $e(strtoupper(substr($ownerName, 0, 1))); ?></span><?php echo $e($ownerFirst); ?></span>
            <?php else: ?>
                <a class="cc-statelink" href="command-center.php?demo=1&brand=<?php echo $e($key); ?><?php echo $isEmpty ? '' : '&state=empty'; ?>"><?php echo $isEmpty ? 'View with sample data' : 'Preview new-org view'; ?></a>
                <span class="cc-demo">Example &middot; sample data</span>
                <?php if (!$isEmpty): ?><span class="cc-live"><span class="dot"></span>Live</span><?php endif; ?>
                <a class="cc-statelink strong" href="signup.php?mode=login">Sign in</a>
                <span class="cc-admin"><span class="cc-avatar"><?php echo $e(strtoupper(substr($ownerName, 0, 1))); ?></span><?php echo $e($ownerFirst); ?></span>
            <?php endif; ?>
        </div>
    </header>

    <main class="cc-body">

        <!-- Dashboard -->
        <section class="cc-panel active" data-panel="dashboard" aria-label="Dashboard">
        <?php if ($isEmpty): ?>
            <div class="cc-empty">
                <div class="cc-empty-card">
                    <span class="cc-empty-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l19-9-9 19-2-8-8-2z"/></svg></span>
                    <h1>Welcome to your Command Center</h1>
                    <p>You don&rsquo;t have an auction yet. Let&rsquo;s set up your first one, it takes about ten minutes, and you can preview everything before you go live.</p>
                    <ol class="cc-setup">
                        <li><b>Name your event &amp; set a goal</b><span>Give it a title, dates, and a fundraising target.</span></li>
                        <li><b>Brand it</b><span>Colors, logo, and cover photo in the Branding studio.</span><button class="cc-setup-go" type="button" data-panel="branding">Open Branding &rarr;</button></li>
                        <li><b>Add your items</b><span>Photos, descriptions, and starting bids.</span></li>
                        <li><b>Invite your team</b><span>Give staff and volunteers the right access.</span></li>
                        <li><b>Open for bidding</b><span>Share the link or print QR codes for guests.</span></li>
                    </ol>
                    <?php if ($LIVE): ?>
                    <form class="cc-createform" data-create-event>
                        <label class="cc-field"><span>Event name</span><input type="text" name="name" required maxlength="255" placeholder="Spring Gala Silent Auction"></label>
                        <label class="cc-field"><span>Event date</span><input type="date" name="event_date" required></label>
                        <button class="cc-btn cc-btn-primary" type="submit">Create your first auction</button>
                        <p class="cc-createform-err" data-create-err role="alert" hidden></p>
                    </form>
                    <?php else: ?>
                    <a class="cc-btn cc-btn-primary" href="signup.php">Create your first auction</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php
                $raisedN = (int)str_replace(',', '', $ev['raised']);
                $goalN   = $ev['goal'] !== null ? (int)str_replace(',', '', $ev['goal']) : null;
                $collN   = (int)str_replace(',', '', $ev['collected']);
                $diff    = $goalN !== null ? $raisedN - $goalN : 0;
                $outK    = round(($raisedN - $collN) / 1000);
                $fill    = $ev['pct'] !== null ? min((int)$ev['pct'], 100) : ($raisedN > 0 ? 100 : 0);
                list($spLine, $spArea, $spEnd) = cc_spark(count($sparkVals) >= 2 ? $sparkVals : [0, 0]);
            ?>
            <div class="cc-panel-head cc-greet">
                <h1 data-greet data-name="<?php echo $e($ownerFirst); ?>">Welcome back, <?php echo $e($ownerFirst); ?></h1>
                <?php if ($goalN !== null): ?>
                <p><b><?php echo $e($ev['headline']); ?></b> is <?php echo (int)$ev['pct'] >= 100 ? 'ahead of goal' : 'climbing toward goal'; ?> &mdash; <b>$<?php echo $e($ev['raised']); ?></b> raised (<?php echo (int)$ev['pct']; ?>%). <b><?php echo (int)$ev['closing']; ?> items</b> close within the hour.</p>
                <?php else: ?>
                <p><b><?php echo $e($ev['headline']); ?></b> has raised <b>$<?php echo $e($ev['raised']); ?></b> so far. <b><?php echo (int)$ev['closing']; ?> items</b> close within the hour. <a href="#settings" data-panel-link="settings">Set a fundraising goal</a> to track progress.</p>
                <?php endif; ?>
            </div>

            <!-- Hero progress -->
            <div class="cc-hero">
                <div class="cc-hero-main">
                    <span class="cc-hero-label">Raised &middot; <?php echo $e($ev['event']); ?></span>
                    <div class="cc-hero-amt"><b>$<?php echo $e($ev['raised']); ?></b><?php if ($ev['pct'] !== null): ?><span class="cc-hero-pill"><?php echo (int)$ev['pct']; ?>% of goal</span><?php endif; ?></div>
                    <div class="cc-hero-bar"><i style="width: <?php echo $fill; ?>%;"></i></div>
                    <?php if ($goalN !== null): ?>
                    <p class="cc-hero-sub"><?php echo $diff >= 0 ? '<b class="up">$' . round($diff / 1000) . 'k over goal</b>' : '<b>$' . round(abs($diff) / 1000) . 'k to go</b>'; ?> &middot; $<?php echo $e($ev['goal']); ?> target</p>
                    <?php else: ?>
                    <p class="cc-hero-sub"><b>$<?php echo $e($ev['collected']); ?></b> collected &middot; no goal set</p>
                    <?php endif; ?>
                </div>
                <div class="cc-hero-side">
                    <div class="cc-countdown" data-countdown<?php if ($LIVE && $endEpoch !== null): ?> data-end="<?php echo (int)$endEpoch; ?>"<?php endif; ?>>
                        <span class="cc-cd-lab"><?php echo ($LIVE && $endEpoch !== null && $endEpoch <= time()) ? 'Auction ended' : 'Auction ends in'; ?></span>
                        <div class="cc-cd-units">
                            <span class="u"><b data-d>02</b><span>days</span></span>
                            <span class="u"><b data-h>15</b><span>hrs</span></span>
                            <span class="u"><b data-m>47</b><span>min</span></span>
                            <span class="u"><b data-s>00</b><span>sec</span></span>
                        </div>
                    </div>
                    <div class="cc-spark">
                        <svg class="cc-spark-svg" viewBox="0 0 160 44" preserveAspectRatio="none" aria-hidden="true">
                            <defs><linearGradient id="ccSpark" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#16A34A" stop-opacity="0.22"/><stop offset="1" stop-color="#16A34A" stop-opacity="0"/></linearGradient></defs>
                            <path d="<?php echo $spArea; ?>" fill="url(#ccSpark)"/>
                            <path d="<?php echo $spLine; ?>" fill="none" stroke="#166534" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="<?php echo $spEnd[0]; ?>" cy="<?php echo $spEnd[1]; ?>" r="3" fill="#166534"/>
                        </svg>
                        <span class="cc-spark-cap"><?php echo $e($sparkCap); ?></span>
                    </div>
                </div>
            </div>

            <!-- Quick actions (in live mode these open the full admin console, where item CRUD, messaging, and closing already work) -->
            <?php $qaTag = $LIVE ? 'a' : 'button'; $qaHref = fn($frag) => $LIVE ? ' href="admin.php' . $frag . '"' : ' type="button"'; ?>
            <div class="cc-quick">
                <<?php echo $qaTag . $qaHref('#items'); ?> data-tip="Create a new auction item with photos and a starting bid."><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span>Add item</<?php echo $qaTag; ?>>
                <<?php echo $qaTag . $qaHref('#users'); ?> data-tip="Send an announcement or reminder to everyone in the room."><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></span>Message bidders</<?php echo $qaTag; ?>>
                <<?php echo $qaTag . $qaHref(''); ?> data-tip="Show a full-screen leaderboard on the projector."><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg></span>Big-screen mode</<?php echo $qaTag; ?>>
                <<?php echo $qaTag . $qaHref('#close'); ?> data-tip="End bidding and start automatic settlement."><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 21V4h13l-2.5 4L18 12H5"/></svg></span>Close auctions</<?php echo $qaTag; ?>>
            </div>

            <!-- Secondary stats -->
            <div class="cc-substats">
                <div class="cc-substat"><span>Active Bidders</span><b><?php echo (int)$ev['bidders']; ?></b><small class="up"><?php echo (int)$ev['today']; ?> joined today</small></div>
                <div class="cc-substat"><span>Items</span><b><?php echo (int)$ev['items']; ?></b><small class="warn"><?php echo (int)$ev['closing']; ?> closing soon</small></div>
                <div class="cc-substat"><span>Collected</span><b>$<?php echo $e($ev['collected']); ?></b><small class="warn">$<?php echo $outK; ?>k outstanding</small></div>
            </div>

            <!-- Needs attention + activity -->
            <div class="cc-grid-2">
                <?php
                    $attRows = [];
                    if ((int)$ev['closing'] > 0) { $attRows[] = ['ic' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>', 'txt' => '<b>' . (int)$ev['closing'] . ' items</b> close within the hour', 'cta' => 'Review', 'go' => 'items']; }
                    if ($outK > 0) { $attRows[] = ['ic' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>', 'txt' => '<b>$' . $outK . 'k</b> in winning bids not yet collected', 'cta' => 'View payments', 'go' => 'payments']; }
                    if ($attMissingPhotos > 0) { $attRows[] = ['ic' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>', 'txt' => '<b>' . (int)$attMissingPhotos . ' items</b> missing photos', 'cta' => 'Add photos', 'go' => $LIVE ? 'admin' : 'items']; }
                ?>
                <div class="cc-card">
                    <div class="cc-card-head"><h3>Needs your attention</h3><span><?php echo count($attRows); ?> item<?php echo count($attRows) === 1 ? '' : 's'; ?></span></div>
                    <div class="cc-att">
                        <?php if (!$attRows): ?>
                            <p class="cc-att-clear">Nothing needs your attention right now. Nice work.</p>
                        <?php endif; ?>
                        <?php foreach ($attRows as $ar): ?>
                        <div class="cc-att-row">
                            <span class="cc-att-ic warn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $ar['ic']; ?></svg></span>
                            <span class="cc-att-txt"><?php echo $ar['txt']; ?></span>
                            <?php if ($ar['go'] === 'admin'): ?>
                                <a class="cc-btn cc-btn-sm" href="admin.php"><?php echo $e($ar['cta']); ?></a>
                            <?php else: ?>
                                <button class="cc-btn cc-btn-sm" type="button" data-panel="<?php echo $e($ar['go']); ?>"><?php echo $e($ar['cta']); ?></button>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="cc-card">
                    <div class="cc-card-head"><h3>Recent Activity</h3><span class="cc-live sm"><span class="dot"></span>Live</span></div>
                    <ul class="cc-feed">
                        <?php foreach (array_slice($activity, 0, 5) as $a): ?>
                            <li><span class="cc-feed-dot" aria-hidden="true"></span><span class="cc-feed-txt"><b><?php echo $e($a['who']); ?></b> bid <b>$<?php echo $e($a['amt']); ?></b> on <?php echo $e($a['item']); ?></span><span class="cc-feed-time"><?php echo $e($a['t']); ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Closeout status strip -->
            <div class="cc-statusstrip">
                <span class="cc-ss-title"><span class="d"></span>Automated closeout ready</span>
                <span class="cc-ss-item">Payments <b>Connected</b></span>
                <span class="cc-ss-item">Receipts <b>Auto-sending</b></span>
                <span class="cc-ss-item">Payout <b><?php echo $e($ev['payout']); ?></b></span>
            </div>
        <?php endif; ?>
        </section>

        <!-- Items -->
        <section class="cc-panel" data-panel="items" aria-label="Items" hidden>
            <div class="cc-panel-head"><h1>Items</h1><p><?php echo $isEmpty ? 'No items yet.' : (int)$ev['items'] . ' items catalogued &middot; ' . (int)$ev['closing'] . ' closing soon.'; ?></p></div>
            <?php if ($isEmpty): ?>
                <div class="cc-emptymini"><p>Your item catalog is empty.</p><?php if ($LIVE): ?><a class="cc-btn cc-btn-primary" href="admin.php#items">Add your first item</a><?php else: ?><a class="cc-btn cc-btn-primary" href="signup.php">Add your first item</a><?php endif; ?></div>
            <?php else: ?>
            <div class="cc-card cc-tablewrap">
                <table class="cc-table">
                    <thead><tr><th>Item</th><th>Category</th><th>Current Bid</th><th>Bids</th><th>Closes In</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td class="strong"><?php echo $e($it['name']); ?></td>
                                <td><span class="cc-pill"><?php echo $e($it['cat']); ?></span></td>
                                <td class="strong">$<?php echo $e($it['bid']); ?></td>
                                <td><?php echo (int)$it['bids']; ?></td>
                                <td><?php echo $e($it['left']); ?></td>
                                <td><?php echo $it['status'] === 'closing' ? '<span class="cc-tag warn">Closing soon</span>' : ($it['status'] === 'closed' ? '<span class="cc-tag muted">Closed</span>' : '<span class="cc-tag ok">Live</span>'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <!-- Bidders -->
        <section class="cc-panel" data-panel="bidders" aria-label="Bidders" hidden>
            <div class="cc-panel-head"><h1>Bidders</h1><p><?php echo $isEmpty ? 'No bidders yet.' : (int)$ev['bidders'] . ' registered guests &middot; ' . (int)$ev['today'] . ' joined today.'; ?></p></div>
            <?php if ($isEmpty): ?>
                <div class="cc-emptymini"><p>No one has registered yet. Bidders appear here once your auction is open and you share the link.</p></div>
            <?php else: ?>
            <div class="cc-card cc-tablewrap">
                <table class="cc-table">
                    <thead><tr><th>Paddle</th><th>Bidder</th><th>Bidding On</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($bidders as $b): ?>
                            <tr><td class="cc-paddle">#<?php echo $e($b['paddle']); ?></td><td class="strong"><?php echo $e($b['name']); ?></td><td><?php echo (int)$b['items']; ?> items</td><td class="strong">$<?php echo $e($b['total']); ?></td><td><span class="cc-tag <?php echo $e($b['tone']); ?>"><?php echo $e($b['status']); ?></span></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <!-- Live Activity -->
        <section class="cc-panel" data-panel="activity" aria-label="Live Activity" hidden>
            <div class="cc-panel-head"><h1>Live Activity <?php if (!$isEmpty): ?><span class="cc-live"><span class="dot"></span>Live</span><?php endif; ?></h1><p>Every bid as it happens across the room.</p></div>
            <?php if ($isEmpty): ?>
                <div class="cc-emptymini"><p>No activity yet. Once bidding opens, every bid streams in here in real time.</p></div>
            <?php else: ?>
            <div class="cc-card">
                <ul class="cc-feed lg">
                    <?php foreach ($activity as $a): ?>
                        <li><span class="cc-feed-dot" aria-hidden="true"></span><span class="cc-feed-txt"><b><?php echo $e($a['who']); ?></b> placed a bid of <b>$<?php echo $e($a['amt']); ?></b> on <?php echo $e($a['item']); ?></span><span class="cc-feed-time"><?php echo $e($a['t']); ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </section>

        <!-- Payments -->
        <section class="cc-panel" data-panel="payments" aria-label="Payments" hidden>
            <div class="cc-panel-head"><h1>Payments</h1><p><?php echo $isEmpty ? 'No payments yet.' : '$' . $e($ev['collected']) . ' collected &middot; ' . (int)$ev['collectedPct'] . '% of winning bids settled.'; ?></p></div>
            <?php if ($isEmpty): ?>
                <div class="cc-emptymini"><p>Payments appear here after your auction closes and winners check out.</p></div>
            <?php else: ?>
            <div class="cc-tiles cc-tiles-3">
                <div class="cc-tile"><span>Collected</span><b>$<?php echo $e($ev['collected']); ?></b><small><?php echo (int)$ev['collectedPct']; ?>% of total</small></div>
                <div class="cc-tile"><span>Outstanding</span><b><?php echo (100 - (int)$ev['collectedPct']); ?>%</b><small>Auto-charging on close</small></div>
                <div class="cc-tile"><span>Next Payout</span><b><?php echo $e($ev['payout']); ?></b><small>Direct to your bank</small></div>
            </div>
            <div class="cc-card cc-tablewrap">
                <table class="cc-table">
                    <thead><tr><th>Bidder</th><th>Method</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr><td class="strong"><?php echo $e($p['who']); ?></td><td><?php echo $e($p['method']); ?></td><td class="strong">$<?php echo $e($p['amt']); ?></td><td><span class="cc-tag <?php echo $e($p['tone']); ?>"><?php echo $e($p['status']); ?></span></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <!-- Reports -->
        <section class="cc-panel" data-panel="reports" aria-label="Reports" hidden>
            <div class="cc-panel-head"><h1>Reports</h1><p>One-click reconciliation, the moment your auction closes.</p></div>
            <div class="cc-reports">
                <?php foreach ($reports as $r): ?>
                    <div class="cc-card cc-report">
                        <div class="cc-report-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/></svg></div>
                        <div class="cc-report-body"><h3><?php echo $e($r['name']); ?></h3><p><?php echo $e($r['desc']); ?></p></div>
                        <?php if ($LIVE && !$isEmpty): ?><a class="cc-btn" href="admin.php#reports">Open</a><?php else: ?><button class="cc-btn" type="button"<?php echo $isEmpty ? ' disabled' : ''; ?>>Download</button><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Branding (Branding Studio, moved in) -->
        <section class="cc-panel" data-panel="branding" aria-label="Branding" hidden>
            <div class="cc-panel-head"><h1>Branding</h1><p>Design your auction&rsquo;s look. Everything updates live on the right.</p></div>
            <div class="cc-branding">
                <div class="cc-brand-controls">
                    <section class="cc-bset">
                        <h4>Brand color</h4>
                        <div class="cc-swatches" data-color-swatches>
                            <?php foreach (['#12395F' => 'Navy', '#C2531F' => 'Terracotta', '#7D1F2E' => 'Crimson', '#1F6B4A' => 'Forest', '#0F766E' => 'Teal', '#5B3FA8' => 'Violet', '#B45309' => 'Amber', '#1F2937' => 'Charcoal'] as $hex => $lab): ?>
                                <button type="button" style="--sw:<?php echo $hex; ?>" data-color="<?php echo $hex; ?>" aria-label="<?php echo $lab; ?>"></button>
                            <?php endforeach; ?>
                        </div>
                        <label class="cc-color-custom"><input type="color" data-color-input value="<?php echo $e($ev['brand']); ?>"><span>Pick a custom color</span></label>
                    </section>
                    <section class="cc-bset">
                        <h4>Typography</h4>
                        <div class="cc-fontlist" data-font-list>
                            <button type="button" data-font="Poppins" data-kind="sans" style="font-family:'Poppins',sans-serif">Poppins</button>
                            <button type="button" data-font="Plus Jakarta Sans" data-kind="sans" style="font-family:'Plus Jakarta Sans',sans-serif">Jakarta</button>
                            <button type="button" data-font="Space Grotesk" data-kind="sans" style="font-family:'Space Grotesk',sans-serif">Grotesk</button>
                            <button type="button" data-font="Playfair Display" data-kind="serif" style="font-family:'Playfair Display',serif">Playfair</button>
                            <button type="button" data-font="Fraunces" data-kind="serif" style="font-family:'Fraunces',serif">Fraunces</button>
                            <button type="button" data-font="Cormorant Garamond" data-kind="serif" style="font-family:'Cormorant Garamond',serif">Cormorant</button>
                        </div>
                    </section>
                    <section class="cc-bset">
                        <h4>Logo &amp; identity</h4>
                        <label class="cc-field"><span>Organization name</span><input type="text" data-org-name value="<?php echo $e($ev['org']); ?>" maxlength="40"></label>
                        <label class="cc-field"><span>Logo initials</span><input type="text" data-org-initials value="<?php echo $e($ev['initials']); ?>" maxlength="3"></label>
                        <label class="cc-upload" data-logo-drop><input type="file" accept="image/png,image/svg+xml,image/jpeg,image/webp" data-org-logo><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/></svg><span>Upload your logo <small>PNG, SVG, JPG</small></span></label>
                    </section>
                    <section class="cc-bset">
                        <h4>Cover photo</h4>
                        <div class="cc-imglist" data-image-list>
                            <?php foreach (['images/items/web/org-health.jpg', 'images/items/web/org-rescue.jpg', 'images/items/web/org-gala.jpg', 'images/items/web/org-conservation.jpg', 'images/items/web/gala.jpg'] as $img): ?>
                                <button type="button" data-img="<?php echo $img; ?>" aria-label="Cover"><img src="<?php echo $img; ?>" alt=""></button>
                            <?php endforeach; ?>
                        </div>
                        <label class="cc-range">
                            <span>Image opacity <em data-opacity-val>100%</em></span>
                            <input type="range" min="15" max="100" value="100" step="5" data-hero-opacity>
                            <small>Lower it for less photo bleed behind your text.</small>
                        </label>
                    </section>
                    <section class="cc-bset">
                        <h4>Hero copy</h4>
                        <label class="cc-field"><span>Headline</span><input type="text" data-hero-head value="<?php echo $e($ev['headline']); ?>" maxlength="42"></label>
                        <label class="cc-field"><span>Tagline</span><input type="text" data-hero-tag value="<?php echo $e($ev['tagline']); ?>" maxlength="60"></label>
                    </section>
                    <?php if ($LIVE): ?>
                    <div class="cc-brand-save"
                         data-branding-live
                         data-org-id="<?php echo (int)$orgId; ?>"
                         data-org-accent="<?php echo $e($orgRow['brand_accent'] ?? '#d99a2b'); ?>"
                         data-org-logo="<?php echo $e($orgRow['logo_url'] ?? ''); ?>"
                         data-org-email="<?php echo $e($orgRow['contact_email'] ?? ''); ?>">
                        <button class="cc-btn cc-btn-primary" type="button" data-branding-save>Save branding</button>
                        <span data-branding-msg>Publishes your color and organization name to your live auction pages. Fonts &amp; cover photos preview here; publishing them is coming soon.</span>
                    </div>
                    <?php else: ?>
                    <div class="cc-brand-save"><button class="cc-btn cc-btn-primary" type="button">Save branding</button><span>Changes preview live &middot; save to publish</span></div>
                    <?php endif; ?>
                </div>

                <div class="cc-brand-preview">
                    <div class="cc-bp" data-preview style="--bp-brand:<?php echo $e($ev['brand']); ?>; --bp-brand2:<?php echo $e($bdark($ev['brand'])); ?>; --bp-font:'<?php echo $e($ev['font']); ?>',sans-serif; --bp-img:url('<?php echo $e($ev['hero']); ?>'); --bp-img-op:1;">
                        <div class="cc-bp-bar"><span></span><span></span><span></span><em>your-auction.silentbidpro.com</em></div>
                        <div class="cc-bp-hero">
                            <div class="cc-bp-herocopy">
                                <span class="cc-bp-org"><span class="cc-bp-mark" data-p-initials><?php echo $e($ev['initials']); ?></span><b data-p-org><?php echo $e($ev['org']); ?></b></span>
                                <h3 class="cc-bp-head" data-p-head><?php echo $e($ev['headline']); ?></h3>
                                <p class="cc-bp-tag" data-p-tag><?php echo $e($ev['tagline']); ?></p>
                            </div>
                        </div>
                        <div class="cc-bp-strip">
                            <span class="cc-bp-ring"><b><?php echo (int)$ev['pct']; ?>%</b></span>
                            <div class="cc-bp-fig"><b>$<?php echo $e($ev['raised']); ?></b><span>raised of $<?php echo $e($ev['goal']); ?></span></div>
                            <button class="cc-bp-btn" type="button">Browse Items</button>
                        </div>
                        <div class="cc-bp-card">
                            <div class="cc-bp-cardimg"><img src="images/items/web/wine.jpg" alt=""></div>
                            <div class="cc-bp-cardbody"><span class="cc-bp-cardtag">Travel</span><b>Napa Vineyard Weekend</b><span class="cc-bp-bid">Current bid $1,250</span><button class="cc-bp-btn cc-bp-btn-block" type="button">Place Bid</button></div>
                        </div>
                    </div>
                    <p class="cc-brand-note">This is exactly what your bidders will see.</p>
                </div>
            </div>
        </section>

        <!-- Subscription -->
        <section class="cc-panel" data-panel="subscription" aria-label="Subscription" hidden>
            <div class="cc-panel-head"><h1>Subscription</h1><p>You&rsquo;re on the <b><?php echo $e($plans[$currentPlan]['label']); ?></b> plan. Upgrade or downgrade anytime.</p></div>
            <div class="cc-plans">
                <?php foreach ($plans as $pk => $pl): $isCur = ($pk === $currentPlan); ?>
                    <div class="cc-plan<?php echo $isCur ? ' current' : ''; ?><?php echo $pk === 'pro' ? ' pop' : ''; ?>">
                        <?php if ($isCur): ?><span class="cc-plan-badge">Current plan</span><?php endif; ?>
                        <h3><?php echo $e($pl['label']); ?></h3>
                        <div class="cc-plan-price"><b><?php echo $e($pl['price']); ?></b><span><?php echo $e($pl['per']); ?></span></div>
                        <?php $isFreeKey = in_array($pk, ['seedling', 'free'], true); ?>
                        <?php if ($isCur): ?>
                            <button class="cc-btn cc-btn-block" type="button" disabled>Your plan</button>
                        <?php elseif ($LIVE && $isFreeKey): ?>
                            <button class="cc-btn cc-btn-block" type="button" data-billing-portal>Downgrade in billing portal</button>
                        <?php elseif ($LIVE): ?>
                            <button class="cc-btn cc-btn-primary cc-btn-block" type="button" data-plan-checkout="<?php echo $e($pk); ?>">Upgrade to <?php echo $e($pl['label']); ?></button>
                        <?php elseif ($pk === 'enterprise'): ?>
                            <button class="cc-btn cc-btn-block" type="button">Contact sales</button>
                        <?php else: ?>
                            <button class="cc-btn cc-btn-primary cc-btn-block" type="button"><?php echo $isFreeKey ? 'Downgrade' : 'Upgrade'; ?></button>
                        <?php endif; ?>
                        <ul class="cc-plan-feats"><?php foreach ($pl['feats'] as $f): ?><li><?php echo $e($f); ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($LIVE): ?>
            <div class="cc-card cc-billnote">
                <div><b>Billing is handled securely by Stripe</b><span>Payment method, invoices, and plan changes live in your billing portal.</span></div>
                <button class="cc-btn" type="button" data-billing-portal>Open billing portal</button>
            </div>
            <?php else: ?>
            <div class="cc-card cc-billnote">
                <div><b>Billed monthly &middot; next charge May 1</b><span>Visa ending 4242 &middot; manage payment method anytime.</span></div>
                <button class="cc-btn" type="button">Billing history</button>
            </div>
            <?php endif; ?>
            <p class="cc-app-note"><span aria-hidden="true">📱</span> In the iPhone app, plan changes open on the web to keep things simple, this section links out to silentbidpro.com.</p>
        </section>

        <!-- Settings & Account -->
        <section class="cc-panel" data-panel="settings" aria-label="Settings and account" hidden>
            <div class="cc-panel-head"><h1>Settings &amp; Account</h1><p>Payouts, your team, and event details.</p></div>
            <div class="cc-settings">
                <?php if ($LIVE): ?>
                <div class="cc-card">
                    <div class="cc-card-head"><h3>Payments</h3><span class="cc-tag ok">Stripe</span></div>
                    <div class="cc-row"><span class="lab">Processor<span>Card payments &amp; receipts</span></span><span class="cc-set-val">Stripe</span></div>
                    <div class="cc-row"><span class="lab">Payment mode<span>For the selected event</span></span><span class="cc-set-val"><?php echo $e(ucfirst((string)($sel['payment_mode'] ?? 'standard'))); ?></span></div>
                    <div class="cc-row"><span class="lab">Payout details<span>Managed in your Stripe account</span></span><span class="cc-set-val">stripe.com</span></div>
                    <a class="cc-btn" href="https://dashboard.stripe.com" target="_blank" rel="noopener">Open Stripe dashboard</a>
                </div>
                <?php else: ?>
                <div class="cc-card">
                    <div class="cc-card-head"><h3>Payouts</h3><span class="cc-tag ok">Connected</span></div>
                    <div class="cc-row"><span class="lab">Bank account<span>Where your funds land</span></span><span class="cc-set-val">Chase &bull;&bull; 4021</span></div>
                    <div class="cc-row"><span class="lab">Payout schedule<span>Automatic after each event</span></span><span class="cc-set-val">2 business days</span></div>
                    <div class="cc-row"><span class="lab">Platform fee<span>Per successful payment</span></span><span class="cc-set-val">Included in plan</span></div>
                    <button class="cc-btn" type="button">Update payout details</button>
                </div>
                <?php endif; ?>
                <div class="cc-card">
                    <div class="cc-card-head"><h3>Team</h3><button class="cc-btn cc-btn-sm" type="button">Invite member</button></div>
                    <ul class="cc-team">
                        <?php foreach ($team as $m): ?>
                            <li><span class="cc-team-av"><?php echo $e(strtoupper(substr($m['name'], 0, 1))); ?></span><span class="cc-team-id"><b><?php echo $e($m['name']); ?></b><span><?php echo $e($m['email']); ?></span></span><span class="cc-tag <?php echo $m['role'] === 'Owner' ? 'ok' : 'muted'; ?>"><?php echo $e($m['role']); ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php if ($LIVE && $sel): ?>
                <form class="cc-card" data-event-form data-event-id="<?php echo (int)$selEventId; ?>" data-org-id="<?php echo (int)$orgId; ?>">
                    <div class="cc-card-head"><h3>Event details</h3></div>
                    <label class="cc-field"><span>Event name</span><input type="text" name="name" required maxlength="255" value="<?php echo $e($sel['name']); ?>"></label>
                    <div class="cc-field-row">
                        <label class="cc-field"><span>Event date</span><input type="date" name="event_date" value="<?php echo $e($sel['event_date'] ? date('Y-m-d', strtotime((string)$sel['event_date'])) : ''); ?>"></label>
                        <label class="cc-field"><span>Fundraising goal ($)</span><input type="number" name="fundraising_goal" min="0" step="1" value="<?php echo $goalSet ? (int)$goalAmt : ''; ?>" placeholder="e.g. 100000"></label>
                    </div>
                    <div class="cc-field-row">
                        <label class="cc-field"><span>Status</span><input type="text" value="<?php echo $e(ucfirst((string)($sel['status'] ?? 'draft'))); ?>" disabled></label>
                    </div>
                    <button class="cc-btn cc-btn-primary" type="submit">Save changes</button>
                    <span class="cc-form-msg" data-event-msg></span>
                </form>
                <?php else: ?>
                <div class="cc-card">
                    <div class="cc-card-head"><h3>Event details</h3></div>
                    <label class="cc-field"><span>Event name</span><input type="text" value="<?php echo $e($ev['event']); ?>"></label>
                    <div class="cc-field-row">
                        <label class="cc-field"><span>Fundraising goal</span><input type="text" value="$<?php echo $e($ev['goal'] ?? '100,000'); ?>"></label>
                        <label class="cc-field"><span>Status</span><input type="text" value="<?php echo $isEmpty ? 'Draft' : 'Open'; ?>"></label>
                    </div>
                    <button class="cc-btn cc-btn-primary" type="button">Save changes</button>
                </div>
                <?php endif; ?>
            </div>
        </section>

    </main>
</div>

<!-- Mobile bottom tab bar (app view) -->
<nav class="cc-tabbar" aria-label="Sections">
    <button class="cc-tab active" type="button" data-panel="dashboard"><span class="ic"><?php echo cc_icon('dashboard'); ?></span>Home</button>
    <button class="cc-tab" type="button" data-panel="items"><span class="ic"><?php echo cc_icon('items'); ?></span>Items</button>
    <button class="cc-tab" type="button" data-panel="bidders"><span class="ic"><?php echo cc_icon('bidders'); ?></span>Bidders</button>
    <button class="cc-tab" type="button" data-panel="payments"><span class="ic"><?php echo cc_icon('payments'); ?></span>Money</button>
    <button class="cc-tab" type="button" data-more><span class="ic"><?php echo cc_icon('more'); ?></span>More</button>
</nav>

<!-- Mobile "More" sheet -->
<div class="cc-sheet" data-sheet hidden>
    <div class="cc-sheet-scrim" data-more-close></div>
    <div class="cc-sheet-panel">
        <div class="cc-sheet-grip" aria-hidden="true"></div>
        <p class="cc-sheet-title">More</p>
        <?php foreach (['activity' => 'Live Activity', 'reports' => 'Reports', 'branding' => 'Branding', 'subscription' => 'Subscription', 'settings' => 'Settings'] as $id => $label): ?>
            <button class="cc-sheet-item" type="button" data-panel="<?php echo $e($id); ?>"><span class="ic"><?php echo cc_icon($id); ?></span><?php echo $e($label); ?></button>
        <?php endforeach; ?>
    </div>
</div>

<script>
(function () {
    var panels = Array.prototype.slice.call(document.querySelectorAll('.cc-panel'));
    var triggers = Array.prototype.slice.call(document.querySelectorAll('[data-panel]'));
    var sheetIds = ['activity', 'reports', 'branding', 'subscription', 'settings'];
    var sheet = document.querySelector('[data-sheet]');

    function show(id) {
        panels.forEach(function (p) {
            var on = p.getAttribute('data-panel') === id;
            p.classList.toggle('active', on);
            if (on) { p.removeAttribute('hidden'); } else { p.setAttribute('hidden', ''); }
        });
        document.querySelectorAll('.cc-nav-item').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-panel') === id); });
        document.querySelectorAll('.cc-tab').forEach(function (b) {
            var t = b.getAttribute('data-panel');
            if (t) b.classList.toggle('active', t === id);
        });
        var moreTab = document.querySelector('.cc-tab[data-more]');
        if (moreTab) moreTab.classList.toggle('active', sheetIds.indexOf(id) !== -1);
        if (history.replaceState) history.replaceState(null, '', '#' + id);
        var body = document.querySelector('.cc-body'); if (body) body.scrollTop = 0;
        window.scrollTo(0, 0);
    }
    triggers.forEach(function (b) {
        if (!b.getAttribute('data-panel')) return;
        b.addEventListener('click', function () { show(b.getAttribute('data-panel')); closeSheet(); closeMenu(); });
    });

    // More sheet
    function openSheet() { if (sheet) { sheet.removeAttribute('hidden'); requestAnimationFrame(function () { sheet.classList.add('open'); }); } }
    function closeSheet() { if (sheet) { sheet.classList.remove('open'); setTimeout(function () { sheet.setAttribute('hidden', ''); }, 220); } }
    var moreBtn = document.querySelector('[data-more]');
    if (moreBtn) moreBtn.addEventListener('click', openSheet);
    document.querySelectorAll('[data-more-close]').forEach(function (el) { el.addEventListener('click', closeSheet); });

    // Mobile hamburger: slide-in nav drawer (the sidebar) on phones
    function openMenu() { document.body.classList.add('cc-menu-open'); }
    function closeMenu() { document.body.classList.remove('cc-menu-open'); }
    document.querySelectorAll('[data-menu-open]').forEach(function (el) { el.addEventListener('click', openMenu); });
    document.querySelectorAll('[data-menu-close]').forEach(function (el) { el.addEventListener('click', closeMenu); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeSheet(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMenu(); });

    var start = (location.hash || '').replace('#', '');
    if (start && document.querySelector('.cc-panel[data-panel="' + start + '"]')) show(start);

    // ---- time-aware greeting ----
    var greet = document.querySelector('[data-greet]');
    if (greet) {
        var hr = new Date().getHours();
        var word = hr < 12 ? 'Good morning' : (hr < 18 ? 'Good afternoon' : 'Good evening');
        greet.textContent = word + ', ' + (greet.getAttribute('data-name') || 'there');
    }

    // ---- live countdown on the dashboard hero ----
    var cd = document.querySelector('[data-countdown]');
    if (cd) {
        // Live mode passes the real auction end (epoch seconds); demo fakes 2d 15h 47m.
        var endAttr = cd.getAttribute('data-end');
        var end = endAttr ? parseInt(endAttr, 10) * 1000 : Date.now() + ((2 * 86400) + (15 * 3600) + (47 * 60)) * 1000;
        var pad = function (n) { return String(n).padStart(2, '0'); };
        var tick = function () {
            var s = Math.max(0, Math.floor((end - Date.now()) / 1000));
            var d = Math.floor(s / 86400); s -= d * 86400;
            var h = Math.floor(s / 3600); s -= h * 3600;
            var m = Math.floor(s / 60); var sec = s - m * 60;
            var set = function (sel, v) { var el = cd.querySelector(sel); if (el) el.textContent = pad(v); };
            set('[data-d]', d); set('[data-h]', h); set('[data-m]', m); set('[data-s]', sec);
        };
        tick(); setInterval(tick, 1000);
    }

    // ---- Branding studio live preview ----
    var pv = document.querySelector('[data-preview]');
    if (pv) {
        function toRgb(h) { h = h.replace('#', ''); return [parseInt(h.slice(0,2),16), parseInt(h.slice(2,4),16), parseInt(h.slice(4,6),16)]; }
        function darken(hex, f) { var c = toRgb(hex); return '#' + c.map(function (x) { return Math.max(0, Math.round(x * f)).toString(16).padStart(2, '0'); }).join(''); }
        var scope = document.querySelector('.cc-panel[data-panel="branding"]');
        function setColor(hex) {
            pv.style.setProperty('--bp-brand', hex);
            pv.style.setProperty('--bp-brand2', darken(hex, 0.5));
            var input = scope.querySelector('[data-color-input]'); if (input) input.value = hex;
            scope.querySelectorAll('[data-color]').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-color').toLowerCase() === hex.toLowerCase()); });
        }
        scope.querySelectorAll('[data-color]').forEach(function (b) { b.addEventListener('click', function () { setColor(b.getAttribute('data-color')); }); });
        scope.querySelector('[data-color-input]').addEventListener('input', function () { setColor(this.value); });

        var loaded = { 'Plus Jakarta Sans': true, 'Poppins': true };
        scope.querySelectorAll('[data-font]').forEach(function (b) {
            b.addEventListener('click', function () {
                var name = b.getAttribute('data-font'), kind = b.getAttribute('data-kind');
                if (!loaded[name]) { var l = document.createElement('link'); l.rel = 'stylesheet'; l.href = 'https://fonts.googleapis.com/css2?family=' + name.replace(/ /g, '+') + ':wght@500;600;700&display=swap'; document.head.appendChild(l); loaded[name] = true; }
                pv.style.setProperty('--bp-font', "'" + name + "', " + (kind === 'serif' ? 'serif' : 'sans-serif'));
                scope.querySelectorAll('[data-font]').forEach(function (x) { x.classList.toggle('on', x === b); });
            });
        });
        function bindText(sel, targetSel, up) { var i = scope.querySelector(sel); if (!i) return; i.addEventListener('input', function () { var v = this.value || ''; pv.querySelectorAll(targetSel).forEach(function (el) { el.textContent = up ? (v || '').toUpperCase() : v; }); }); }
        bindText('[data-org-name]', '[data-p-org]', false);
        bindText('[data-org-initials]', '[data-p-initials]', true);
        bindText('[data-hero-head]', '[data-p-head]', false);
        bindText('[data-hero-tag]', '[data-p-tag]', false);
        scope.querySelectorAll('[data-image-list] [data-img]').forEach(function (b) {
            b.addEventListener('click', function () {
                pv.style.setProperty('--bp-img', "url('" + b.getAttribute('data-img') + "')");
                scope.querySelectorAll('[data-image-list] [data-img]').forEach(function (x) { x.classList.toggle('on', x === b); });
            });
        });
        var op = scope.querySelector('[data-hero-opacity]');
        if (op) op.addEventListener('input', function () {
            pv.style.setProperty('--bp-img-op', this.value / 100);
            var lab = scope.querySelector('[data-opacity-val]'); if (lab) lab.textContent = this.value + '%';
        });
        var logoInput = scope.querySelector('[data-org-logo]');
        if (logoInput) logoInput.addEventListener('change', function () {
            var f = this.files && this.files[0]; if (!f) return;
            var r = new FileReader(); r.onload = function (ev) {
                pv.querySelectorAll('[data-p-initials]').forEach(function (m) { m.style.backgroundImage = "url('" + ev.target.result + "')"; m.style.backgroundSize = 'cover'; m.textContent = ''; });
            }; r.readAsDataURL(f);
        });
    }
})();
</script>
<?php if ($LIVE): ?>
<script>
// ---- LIVE MODE: real actions against the existing admin APIs ----
(function () {
    function postJSON(url, body) {
        return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify(body) })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); });
    }

    // Sign out (30-day session cookie is cleared server-side). Two links: topbar + drawer.
    document.querySelectorAll('[data-signout]').forEach(function (out) {
        out.addEventListener('click', function (ev) {
            ev.preventDefault();
            postJSON('api/admin/logout-account.php', {}).catch(function () {}).then(function () { location.href = 'index.php'; });
        });
    });

    // Create first event (empty state).
    var cf = document.querySelector('[data-create-event]');
    if (cf) cf.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var errEl = cf.querySelector('[data-create-err]');
        var orgId = <?php echo (int)$orgId; ?>;
        postJSON('api/admin/update-branding.php?action=update_event', {
            organization_id: orgId,
            name: cf.querySelector('[name="name"]').value,
            event_date: cf.querySelector('[name="event_date"]').value
        }).then(function (res) {
            if (res.ok && res.d.status === 'ok') { location.href = 'command-center.php?event=' + res.d.data.event_id; }
            else { errEl.textContent = (res.d && res.d.message) || 'Could not create the event.'; errEl.hidden = false; }
        }).catch(function () { errEl.textContent = 'Could not create the event.'; errEl.hidden = false; });
    });

    // Branding: publish color + organization name (propagates to all event pages).
    var bwrap = document.querySelector('[data-branding-live]');
    if (bwrap) {
        var bbtn = bwrap.querySelector('[data-branding-save]');
        var bmsg = bwrap.querySelector('[data-branding-msg]');
        bbtn.addEventListener('click', function () {
            var scope = document.querySelector('.cc-panel[data-panel="branding"]');
            var color = scope.querySelector('[data-color-input]').value;
            var name = scope.querySelector('[data-org-name]').value.trim();
            if (!name) { bmsg.textContent = 'Organization name is required.'; return; }
            bbtn.disabled = true; bmsg.textContent = 'Publishing…';
            postJSON('api/admin/update-branding.php?action=update_organization', {
                organization_id: parseInt(bwrap.getAttribute('data-org-id'), 10),
                name: name,
                brand_primary: color,
                brand_accent: bwrap.getAttribute('data-org-accent') || '#d99a2b',
                logo_url: bwrap.getAttribute('data-org-logo') || '',
                contact_email: bwrap.getAttribute('data-org-email') || ''
            }).then(function (res) {
                bbtn.disabled = false;
                bmsg.textContent = (res.ok && res.d.status === 'ok')
                    ? 'Published! Your live auction pages now use this branding.'
                    : ((res.d && res.d.message) || 'Could not save branding.');
            }).catch(function () { bbtn.disabled = false; bmsg.textContent = 'Could not save branding.'; });
        });
    }

    // Subscription: Stripe Checkout upgrade / billing portal.
    document.querySelectorAll('[data-plan-checkout]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.disabled = true;
            postJSON('api/billing/create-checkout.php', { org_id: <?php echo (int)$orgId; ?>, plan: btn.getAttribute('data-plan-checkout') })
                .then(function (res) {
                    if (res.ok && res.d.status === 'ok' && res.d.url) { location.href = res.d.url; }
                    else { btn.disabled = false; alert((res.d && res.d.message) || 'Could not start checkout.'); }
                }).catch(function () { btn.disabled = false; });
        });
    });
    document.querySelectorAll('[data-billing-portal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.disabled = true;
            postJSON('api/billing/portal.php', { org_id: <?php echo (int)$orgId; ?> })
                .then(function (res) {
                    if (res.ok && res.d.status === 'ok' && res.d.url) { location.href = res.d.url; }
                    else { btn.disabled = false; alert((res.d && res.d.message) || 'Could not open the billing portal.'); }
                }).catch(function () { btn.disabled = false; });
        });
    });

    // Event details save (name, date, fundraising goal).
    var ef = document.querySelector('[data-event-form]');
    if (ef) ef.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var msg = ef.querySelector('[data-event-msg]');
        msg.textContent = 'Saving…';
        postJSON('api/admin/update-branding.php?action=update_event', {
            organization_id: parseInt(ef.getAttribute('data-org-id'), 10),
            event_id: parseInt(ef.getAttribute('data-event-id'), 10),
            name: ef.querySelector('[name="name"]').value,
            event_date: ef.querySelector('[name="event_date"]').value,
            fundraising_goal: ef.querySelector('[name="fundraising_goal"]').value
        }).then(function (res) {
            msg.textContent = (res.ok && res.d.status === 'ok') ? 'Saved.' : ((res.d && res.d.message) || 'Could not save.');
            if (res.ok && res.d.status === 'ok') { setTimeout(function () { location.reload(); }, 600); }
        }).catch(function () { msg.textContent = 'Could not save.'; });
    });
})();
</script>
<?php endif; ?>
</body>
</html>
<?php
function cc_icon($id) {
    $p = [
        'dashboard'    => '<rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/>',
        'items'        => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'bidders'      => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0111 0"/><path d="M16 5.2a3.2 3.2 0 010 5.8"/><path d="M18 20a5.5 5.5 0 00-3-4.9"/>',
        'activity'     => '<path d="M3 12h4l2.5 7 5-16 2.5 9H21"/>',
        'payments'     => '<rect x="2.5" y="5" width="19" height="14" rx="2.2"/><path d="M2.5 10h19"/>',
        'reports'      => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/>',
        'branding'     => '<path d="M12 2s6 6 6 10a6 6 0 01-12 0c0-4 6-10 6-10z"/>',
        'subscription' => '<path d="M12 3l2.4 5.3L20 9l-4 3.9.9 5.6L12 16l-4.9 2.5.9-5.6L4 9l5.6-.7z"/>',
        'settings'     => '<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 00-.1-1.2l2-1.6-2-3.4-2.4 1a7 7 0 00-2-1.2L14 2h-4l-.5 2.4a7 7 0 00-2 1.2l-2.4-1-2 3.4 2 1.6A7 7 0 005 12a7 7 0 00.1 1.2l-2 1.6 2 3.4 2.4-1a7 7 0 002 1.2L10 22h4l.5-2.4a7 7 0 002-1.2l2.4 1 2-3.4-2-1.6A7 7 0 0019 12z"/>',
        'more'         => '<circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ($p[$id] ?? '') . '</svg>';
}

// Build a small sparkline: returns [linePath, areaPath, [endX, endY]] for an SVG 160x44 box.
function cc_spark(array $vals, $w = 160, $h = 44) {
    $min = min($vals); $max = max($vals); $range = ($max - $min) ?: 1;
    $n = count($vals); $step = $n > 1 ? $w / ($n - 1) : 0;
    $pts = [];
    foreach (array_values($vals) as $i => $v) {
        $x = round($i * $step, 1);
        $y = round($h - (($v - $min) / $range) * ($h - 6) - 3, 1);
        $pts[] = [$x, $y];
    }
    $line = 'M' . implode(' L', array_map(fn($p) => $p[0] . ',' . $p[1], $pts));
    $area = $line . ' L' . $w . ',' . $h . ' L0,' . $h . ' Z';
    return [$line, $area, end($pts)];
}
