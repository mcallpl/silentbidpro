<?php
// ============================================================
// RECOMPUTE BUYER'S-PREMIUM TIERS
// Sets each organization's premium_rate_bps from its trailing-12-month gross
// auction volume (paid transactions): $0–25k → 2% · –250k → 4% · 250k+ → 5%.
//
// Run nightly via cron:
//   0 3 * * * cd /var/www/html/silentbidpro && /usr/bin/php scripts/recompute-premium-tiers.php >> logs/premium-tiers.log 2>&1
// ============================================================

// SECURITY: CLI only. It mutates org billing config; never expose over the web.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db-helpers.php';
require_once __DIR__ . '/../includes/money.php';

$stamp = date('Y-m-d H:i:s');

// Trailing-12-month PAID volume per org, in cents. Prefer the new bid_cents;
// fall back to the DECIMAL amount for transactions predating the money model.
$rows = dbGetAll(
    "SELECT o.id AS org_id, o.name,
            COALESCE(SUM(COALESCE(t.bid_cents, ROUND(t.amount * 100))), 0) AS vol_cents
     FROM organizations o
     LEFT JOIN events e       ON e.organization_id = o.id
     LEFT JOIN items i        ON i.event_id = e.id
     LEFT JOIN transactions t ON t.item_id = i.id
                              AND t.status = 'paid'
                              AND t.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
     GROUP BY o.id, o.name"
);

$updated = 0;
foreach ($rows as $r) {
    $vol = (int)$r['vol_cents'];
    $bps = premiumRateBpsForVolume($vol);
    dbUpdate(
        "UPDATE organizations
         SET trailing_12mo_volume_cents = ?, premium_rate_bps = ?, premium_tier_computed_at = NOW()
         WHERE id = ?",
        [$vol, $bps, (int)$r['org_id']]
    );
    $updated++;
    printf("[%s] org #%d %-40s vol=%s -> %.2f%%\n",
        $stamp, (int)$r['org_id'], substr($r['name'], 0, 40),
        centsToDisplay($vol), $bps / 100);
}

echo "[$stamp] Recomputed premium tiers for {$updated} organization(s).\n";
