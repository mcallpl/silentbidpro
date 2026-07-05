<?php
// ============================================================
// SAAS PLAN GATING TESTS
// Run: php tests/plans_test.php
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db-helpers.php';
require_once __DIR__ . '/../includes/plans.php';

$pass = 0; $fail = 0; $failures = [];
function check($c, $m) { global $pass,$fail,$failures; if ($c) $pass++; else { $fail++; $failures[]=$m; } }

// ---- Catalog + pure helpers (no DB) ----
check(normalizePlan('PRO') === 'pro', 'normalizePlan case-insensitive');
check(normalizePlan('bogus') === 'free', 'normalizePlan defaults to free');
check(normalizePlan(null) === 'free', 'normalizePlan(null) = free');

check(planFeatures('free')['max_active_events'] === 1, 'free: 1 active event');
check(planFeatures('pro')['max_active_events'] === 3, 'pro: 3 active events');
check(planFeatures('enterprise')['max_active_events'] === null, 'enterprise: unlimited');

check(planFeatures('free')['custom_branding'] === false, 'free: no custom branding');
check(planFeatures('pro')['custom_branding'] === true, 'pro: custom branding');
check(planFeatures('free')['csv_export'] === false, 'free: no CSV export');
check(planFeatures('enterprise')['sso'] === true, 'enterprise: SSO');
check(planFeatures('pro')['sso'] === false, 'pro: no SSO');

check(minPlanForFeature('custom_branding') === 'pro', 'min plan for custom branding = pro');
check(minPlanForFeature('sso') === 'enterprise', 'min plan for SSO = enterprise');
check(minPlanForFeature('csv_export') === 'pro', 'min plan for CSV = pro');

// ---- DB-backed limit logic (seeded, rolled back) ----
$db = getDB();
$db->begin_transaction();

$org_id = dbInsert("INSERT INTO organizations (name, slug, plan, created_at) VALUES (?,?,?,NOW())",
                   ['Gate Test Org', 'gate-test-'.substr(md5('g'),0,6), 'free']);

function mkEvent($org_id, $status) {
    return dbInsert("INSERT INTO events (organization_id, name, slug, status, auction_end_time, created_at)
                     VALUES (?,?,?,?, DATE_ADD(NOW(), INTERVAL 1 DAY), NOW())",
                    [$org_id, 'Ev', 'ev-'.bin2hex(random_bytes(4)), $status]);
}

// One open event already.
$e1 = mkEvent($org_id, 'open');
$e2 = mkEvent($org_id, 'draft');

check(orgActiveEventCount($org_id) === 1, 'active count = 1');
check(getOrgPlan($org_id) === 'free', 'org plan = free');

// FREE (limit 1): can't open a 2nd, but re-saving the open one is fine.
check(orgCanOpenAnotherEvent($org_id, $e2) === false, 'free: cannot open 2nd event');
check(orgCanOpenAnotherEvent($org_id, $e1) === true,  'free: re-saving already-open event OK');
check(orgCan($org_id, 'custom_branding') === false, 'free org: custom_branding gated');

// Upgrade to PRO (limit 3): can open up to 3.
dbUpdate("UPDATE organizations SET plan='pro' WHERE id=?", [$org_id]);
check(getOrgPlan($org_id) === 'pro', 'org plan now pro');
check(orgCanOpenAnotherEvent($org_id, $e2) === true, 'pro: can open a 2nd event');
check(orgCan($org_id, 'custom_branding') === true, 'pro org: custom_branding enabled');
check(orgCan($org_id, 'sso') === false, 'pro org: SSO still gated');
// Fill to 3 open, then a 4th is blocked.
dbUpdate("UPDATE events SET status='open' WHERE id=?", [$e2]);
$e3 = mkEvent($org_id, 'open');
$e4 = mkEvent($org_id, 'draft');
check(orgActiveEventCount($org_id) === 3, 'pro: 3 open now');
check(orgCanOpenAnotherEvent($org_id, $e4) === false, 'pro: 4th blocked at limit 3');

// Upgrade to ENTERPRISE (unlimited).
dbUpdate("UPDATE organizations SET plan='enterprise' WHERE id=?", [$org_id]);
check(orgCanOpenAnotherEvent($org_id, $e4) === true, 'enterprise: unlimited events');
check(orgCan($org_id, 'sso') === true, 'enterprise org: SSO enabled');

$db->rollback();

echo "PASS: $pass   FAIL: $fail\n";
if ($fail) { echo "\nFAILURES:\n - ".implode("\n - ", $failures)."\n"; exit(1); }
echo "✅ SaaS plan gating holds.\n";
