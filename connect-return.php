<?php
// ============================================================
// STRIPE CONNECT — return landing
// Stripe sends the organizer back here after (or mid-way through)
// Express onboarding. We verify the LIVE account state with Stripe
// (never trust the redirect alone), persist it, and land them in
// the Command Center with a conclusive confirmation banner.
//   ?refresh=1 -> the onboarding link expired; mint a new one and bounce back
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-helpers.php';
require_once __DIR__ . '/includes/admin-auth-middleware.php';
require_once __DIR__ . '/includes/connect.php';

$admin = getCurrentAdmin();
if (!$admin) {
    header('Location: signup.php?mode=login');
    exit;
}
$org_id = (int)($_GET['org'] ?? 0) ?: (int)($admin['organization_id'] ?? 0);
if (!$org_id || !checkAdminOrgAccess($admin['id'], $org_id)) {
    header('Location: command-center.php');
    exit;
}

// Expired/abandoned link: hand them a fresh one and put them right back in.
if (!empty($_GET['refresh'])) {
    $base = rtrim(PUBLIC_SITE_URL, '/');
    $r = startConnectOnboarding($org_id,
        $base . '/connect-return.php?org=' . $org_id,
        $base . '/connect-return.php?org=' . $org_id . '&refresh=1');
    if (!empty($r['url'])) { header('Location: ' . $r['url']); exit; }
    header('Location: command-center.php?connected=error#settings');
    exit;
}

// THE conclusive check: ask Stripe what this account can actually do.
$status = refreshOrgConnectStatus($org_id);
$flag = 'pending';
if ($status && (int)$status['stripe_charges_enabled'] === 1 && (int)$status['stripe_payouts_enabled'] === 1) {
    $flag = 'yes';
} elseif ($status && $status['stripe_onboarding_status'] === 'restricted') {
    $flag = 'restricted';
}
header('Location: command-center.php?connected=' . $flag . '#settings');
exit;
