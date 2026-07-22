<?php
// ============================================================
// SILENT BID PRO — Organizer Sign Up / Sign In
// Creates a real organization + organizer account
// (api/auth/register-organizer.php), or signs an existing
// organizer in (api/admin/login-account.php).
//   ?plan=pro|enterprise -> after auth, jump into Stripe checkout
//   ?mode=login          -> open on the sign-in tab
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-helpers.php';
require_once __DIR__ . '/includes/admin-auth-middleware.php';

$plan = in_array(($_GET['plan'] ?? ''), ['pro', 'enterprise'], true) ? $_GET['plan'] : '';
$mode = ($_GET['mode'] ?? '') === 'login' ? 'login' : 'signup';

// Already signed in? Straight to the Command Center (or checkout if a plan was chosen).
$admin = getCurrentAdmin();
if ($admin && !$plan) {
    header('Location: command-center.php');
    exit;
}
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Get Started — Silent Bid Pro</title>
    <meta name="robots" content="noindex">
    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/landing.css?v=<?php echo @filemtime(__DIR__ . '/css/landing.css') ?: '1'; ?>">
    <style>
        .su-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; background: #FAFAFB; }
        .su-card { width: 100%; max-width: 440px; background: #fff; border: 1px solid #E3E3E6; border-radius: 16px; padding: 32px 28px; }
        .su-logo { display: block; margin: 0 auto 18px; width: 168px; }
        .su-card h1 { font-family: 'Playfair Display', serif; font-size: 26px; text-align: center; margin: 0 0 6px; color: #1D1D1F; }
        .su-sub { text-align: center; color: #6E6E73; font-size: 14.5px; margin: 0 0 22px; }
        .su-plan { display: block; text-align: center; background: #F2F7F4; color: #14532D; border: 1px solid #CFE3D6; border-radius: 10px; padding: 8px 12px; font-size: 13.5px; font-weight: 600; margin-bottom: 18px; }
        .su-tabs { display: flex; gap: 6px; background: #F4F4F6; border-radius: 10px; padding: 4px; margin-bottom: 20px; }
        .su-tabs button { flex: 1; border: 0; background: transparent; border-radius: 8px; padding: 9px 0; font: 600 14px 'Plus Jakarta Sans', sans-serif; color: #6E6E73; cursor: pointer; }
        .su-tabs button.on { background: #fff; color: #1D1D1F; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .su-field { display: block; margin-bottom: 14px; }
        .su-field span { display: block; font-size: 13px; font-weight: 600; color: #1D1D1F; margin-bottom: 5px; }
        .su-field input { width: 100%; box-sizing: border-box; border: 1px solid #E3E3E6; border-radius: 10px; padding: 11px 12px; font: 15px 'Plus Jakarta Sans', sans-serif; color: #1D1D1F; background: #fff; }
        .su-field input:focus { outline: 2px solid #14532D; outline-offset: -1px; }
        .su-hp { position: absolute; left: -9999px; opacity: 0; }
        .su-btn { width: 100%; border: 0; border-radius: 12px; padding: 13px 0; background: #0C100E; color: #A9CBB8; font: 700 15px 'Plus Jakarta Sans', sans-serif; cursor: pointer; margin-top: 6px; }
        .su-btn[disabled] { opacity: .6; cursor: wait; }
        .su-err { display: none; background: #FDF1F1; color: #8C2B2B; border: 1px solid #EFCBCB; border-radius: 10px; padding: 10px 12px; font-size: 13.5px; margin-bottom: 14px; }
        .su-err.show { display: block; }
        .su-foot { text-align: center; margin-top: 18px; font-size: 13.5px; color: #6E6E73; }
        .su-foot a { color: #14532D; font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
<div class="su-wrap">
    <main class="su-card" aria-labelledby="suTitle">
        <a href="index.php"><img class="su-logo" src="images/brand/silentbidpro-logo-black.png" alt="Silent Bid Pro"></a>
        <h1 id="suTitle" data-title>Create your Command Center</h1>
        <p class="su-sub" data-sub>Set up your organization in about a minute.</p>
        <?php if ($plan): ?>
            <span class="su-plan">Selected plan: <?php echo $e(ucfirst($plan)); ?> &middot; you&rsquo;ll confirm payment on the next step</span>
        <?php endif; ?>

        <div class="su-tabs" role="tablist" aria-label="Sign up or sign in">
            <button type="button" role="tab" data-tab="signup" aria-selected="true">Create account</button>
            <button type="button" role="tab" data-tab="login" aria-selected="false">Sign in</button>
        </div>

        <p class="su-err" data-err role="alert"></p>

        <form data-form="signup" autocomplete="on">
            <label class="su-field"><span>Organization name</span><input type="text" name="org_name" required maxlength="255" placeholder="Greenfield Education Fund"></label>
            <label class="su-field"><span>Your name</span><input type="text" name="full_name" required maxlength="255" autocomplete="name"></label>
            <label class="su-field"><span>Email</span><input type="email" name="email" required autocomplete="email"></label>
            <label class="su-field"><span>Username</span><input type="text" name="username" required minlength="3" maxlength="60" pattern="[A-Za-z0-9._\-]+" autocomplete="username"></label>
            <label class="su-field"><span>Password</span><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
            <label class="su-hp" aria-hidden="true">Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            <button class="su-btn" type="submit">Create my account</button>
        </form>

        <form data-form="login" hidden autocomplete="on">
            <label class="su-field"><span>Username</span><input type="text" name="username" required autocomplete="username"></label>
            <label class="su-field"><span>Password</span><input type="password" name="password" required autocomplete="current-password"></label>
            <button class="su-btn" type="submit">Sign in</button>
        </form>

        <p class="su-foot">Bidding at an event? <a href="bid.php">Bidder sign-in is here</a>.</p>
    </main>
</div>

<script>
(function () {
    var PLAN = <?php echo json_encode($plan); ?>;
    var ALREADY = <?php echo json_encode((bool)$admin); ?>;
    var err = document.querySelector('[data-err]');
    var tabs = document.querySelectorAll('[data-tab]');
    var forms = { signup: document.querySelector('[data-form="signup"]'), login: document.querySelector('[data-form="login"]') };
    var title = document.querySelector('[data-title]'), sub = document.querySelector('[data-sub]');

    function setMode(m) {
        tabs.forEach(function (t) { var on = t.getAttribute('data-tab') === m; t.classList.toggle('on', on); t.setAttribute('aria-selected', on ? 'true' : 'false'); });
        forms.signup.hidden = m !== 'signup';
        forms.login.hidden = m !== 'login';
        title.textContent = m === 'signup' ? 'Create your Command Center' : 'Welcome back';
        sub.textContent = m === 'signup' ? 'Set up your organization in about a minute.' : 'Sign in to your organizer account.';
        err.classList.remove('show');
    }
    tabs.forEach(function (t) { t.addEventListener('click', function () { setMode(t.getAttribute('data-tab')); }); });
    setMode(<?php echo json_encode($mode); ?>);

    function fail(msg) { err.textContent = msg || 'Something went wrong. Please try again.'; err.classList.add('show'); }

    // After auth: either straight to the Command Center, or into Stripe checkout for the chosen plan.
    function next(orgId) {
        if (!PLAN) { location.href = 'command-center.php'; return; }
        var body = { plan: PLAN };
        if (orgId) body.org_id = orgId;
        fetch('api/billing/create-checkout.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.status === 'ok' && d.url) { location.href = d.url; }
                else { location.href = 'command-center.php#subscription'; }
            })
            .catch(function () { location.href = 'command-center.php#subscription'; });
    }

    // Already signed in with a plan in the URL -> go straight to checkout for their org.
    if (ALREADY && PLAN) { next(<?php echo json_encode($admin ? (int)($admin['organization_id'] ?? 0) : 0); ?> || null); }

    function wire(form, url, build) {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            err.classList.remove('show');
            var btn = form.querySelector('.su-btn'); btn.disabled = true;
            fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(build(form)) })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
                .then(function (res) {
                    if (!res.ok || res.d.status !== 'ok') { btn.disabled = false; fail(res.d.message); return; }
                    next(res.d.organization_id || null);
                })
                .catch(function () { btn.disabled = false; fail(); });
        });
    }
    function val(f, n) { return f.querySelector('[name="' + n + '"]').value; }
    wire(forms.signup, 'api/auth/register-organizer.php', function (f) {
        return { org_name: val(f, 'org_name'), full_name: val(f, 'full_name'), email: val(f, 'email'), username: val(f, 'username'), password: val(f, 'password'), website: val(f, 'website') };
    });
    wire(forms.login, 'api/admin/login-account.php', function (f) {
        return { username: val(f, 'username'), password: val(f, 'password') };
    });
})();
</script>
</body>
</html>
