<?php
// ============================================================
// BILLING & PLANS — web-only SaaS pricing / upgrade page for organizers.
// Never linked from the iOS app (Apple compliance: no in-app pricing/purchase).
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/includes/admin-auth-middleware.php';
require_once __DIR__ . '/includes/plans.php';

$admin = getCurrentAdmin();
if (!$admin) {
    header('Location: admin.php');
    exit;
}

$orgs = getAdminAccessibleOrganizations((int)$admin['id']);
$selected_org_id = (int)($_GET['org_id'] ?? 0);
if (!$selected_org_id && !empty($orgs)) {
    $selected_org_id = (int)$orgs[0]['id'];
}
$org = $selected_org_id ? dbGetRow(
    "SELECT id, name, plan, subscription_status, stripe_subscription_id FROM organizations WHERE id = ?",
    [$selected_org_id]
) : null;

$current_plan = $org ? normalizePlan($org['plan']) : 'free';
$has_subscription = $org && !empty($org['stripe_subscription_id']);
$catalog = planCatalog();

$feature_labels = [
    'max_active_events' => 'active events at once',
    'custom_branding'   => 'Custom branding',
    'csv_export'        => 'CSV exports',
    'bigscreen_display' => 'Big-screen display mode',
    'multi_chapter'     => 'Multi-chapter management',
    'api_access'        => 'API access',
    'sso'               => 'SSO',
    'priority_support'  => 'Priority support',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Plans &amp; Billing — <?php echo htmlspecialchars(APP_NAME); ?></title>
<style>
    :root{--ink:#172235;--line:rgba(23,34,53,.14);--green:#28785f;--blue:#315fcb;--gold:#d99a2b}
    *{box-sizing:border-box}
    body{margin:0;font-family:"Avenir Next","Trebuchet MS",system-ui,sans-serif;color:var(--ink);background:#fffdf8;padding:2rem 1rem}
    .wrap{max-width:1000px;margin:0 auto}
    h1{font-family:Georgia,serif;margin:.2rem 0}
    .sub{color:#4a5568;margin:0 0 1.5rem}
    .banner{padding:.8rem 1rem;border-radius:10px;margin-bottom:1.2rem;font-weight:600}
    .banner.ok{background:#e6f4ee;color:#1c5c47}
    .banner.warn{background:#fdf0d5;color:#7a5a12}
    .orgbar{display:flex;gap:.6rem;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap}
    select{padding:.5rem .7rem;border-radius:8px;border:1px solid var(--line);font-weight:700}
    .status{font-size:.85rem;color:#4a5568}
    .plans{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem}
    .card{border:1px solid var(--line);border-radius:16px;padding:1.4rem;background:#fff;display:flex;flex-direction:column}
    .card.current{border-color:var(--green);box-shadow:0 0 0 2px rgba(40,120,95,.15)}
    .card h2{font-family:Georgia,serif;margin:.1rem 0}
    .price{font-size:1.8rem;font-weight:800;margin:.3rem 0}
    .price small{font-size:.9rem;font-weight:500;color:#718096}
    ul{list-style:none;padding:0;margin:1rem 0;display:grid;gap:.4rem}
    li{font-size:.92rem}li::before{content:"✓ ";color:var(--green);font-weight:800}
    li.no{color:#a0aec0}li.no::before{content:"— ";color:#cbd5e0}
    .cta{margin-top:auto;padding:.7rem 1rem;border-radius:10px;border:0;font-weight:800;cursor:pointer;font-size:1rem}
    .cta.buy{background:var(--green);color:#fff}
    .cta.cur{background:#eef2f7;color:#4a5568;cursor:default}
    .cta.manage{background:transparent;border:1px solid var(--line);margin-top:.5rem}
    .foot{margin-top:1.5rem;font-size:.85rem;color:#718096}
</style>
</head>
<body>
<div class="wrap">
    <h1>Plans &amp; Billing</h1>
    <p class="sub">Choose the plan that fits your organization. Your auction proceeds always go 100% to the charity — plans only unlock organizer tools.</p>

    <?php if (isset($_GET['upgraded'])): ?><div class="banner ok">🎉 Your subscription is active. Thank you for supporting the platform!</div><?php endif; ?>
    <?php if (isset($_GET['canceled'])): ?><div class="banner warn">Checkout canceled — no changes were made.</div><?php endif; ?>

    <div class="orgbar">
        <?php if (count($orgs) > 1): ?>
            <label for="orgSel"><strong>Organization:</strong></label>
            <select id="orgSel" onchange="location.href='billing.php?org_id='+this.value">
                <?php foreach ($orgs as $o): ?>
                    <option value="<?php echo (int)$o['id']; ?>" <?php echo (int)$o['id']===$selected_org_id?'selected':''; ?>>
                        <?php echo htmlspecialchars($o['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php elseif ($org): ?>
            <strong><?php echo htmlspecialchars($org['name']); ?></strong>
        <?php endif; ?>
        <?php if ($org): ?>
            <span class="status">Current plan: <strong><?php echo htmlspecialchars($catalog[$current_plan]['label']); ?></strong>
            <?php if ($org['subscription_status']): ?>(<?php echo htmlspecialchars($org['subscription_status']); ?>)<?php endif; ?></span>
        <?php endif; ?>
    </div>

    <div class="plans">
        <?php foreach (['free','pro','enterprise'] as $pk):
            $p = $catalog[$pk]; $is_current = ($pk === $current_plan); ?>
            <div class="card <?php echo $is_current?'current':''; ?>">
                <h2><?php echo htmlspecialchars($p['label']); ?></h2>
                <div class="price">
                    <?php echo $p['price_monthly_cents']===0 ? 'Free' : '$'.number_format($p['price_monthly_cents']/100).' <small>/mo</small>'; ?>
                </div>
                <ul>
                    <li><?php echo $p['max_active_events']===null ? 'Unlimited' : $p['max_active_events']; ?> active event<?php echo $p['max_active_events']===1?'':'s'; ?> at a time</li>
                    <?php foreach (['custom_branding','csv_export','bigscreen_display','multi_chapter','api_access','sso','priority_support'] as $fk): ?>
                        <li class="<?php echo $p[$fk]?'':'no'; ?>"><?php echo htmlspecialchars($feature_labels[$fk]); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($is_current): ?>
                    <button class="cta cur" disabled>Your current plan</button>
                    <?php if ($has_subscription): ?>
                        <button class="cta manage" onclick="manage()">Manage subscription</button>
                    <?php endif; ?>
                <?php elseif ($pk === 'free'): ?>
                    <?php if ($has_subscription): ?><button class="cta manage" onclick="manage()">Downgrade / cancel</button><?php endif; ?>
                <?php else: ?>
                    <button class="cta buy" onclick="upgrade('<?php echo $pk; ?>', this)">Upgrade to <?php echo htmlspecialchars($p['label']); ?></button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="foot">Billing is handled securely by Stripe. Subscriptions renew monthly and can be canceled anytime.
    <a href="admin.php">← Back to admin</a></p>
</div>

<script>
    var ORG_ID = <?php echo (int)$selected_org_id; ?>;
    function post(url, body){
        return fetch(url, {method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body:JSON.stringify(body)})
            .then(function(r){ return r.json(); });
    }
    function upgrade(plan, btn){
        if (btn){ btn.disabled=true; btn.textContent='Redirecting…'; }
        post('/api/billing/create-checkout.php', {org_id:ORG_ID, plan:plan}).then(function(d){
            if (d.status==='ok' && d.url) location.href=d.url;
            else { alert(d.message||'Could not start checkout'); if(btn){btn.disabled=false;} }
        }).catch(function(){ alert('Network error'); if(btn){btn.disabled=false;} });
    }
    function manage(){
        post('/api/billing/portal.php', {org_id:ORG_ID}).then(function(d){
            if (d.status==='ok' && d.url) location.href=d.url; else alert(d.message||'Could not open billing portal');
        }).catch(function(){ alert('Network error'); });
    }
</script>
</body>
</html>
