<?php require_once __DIR__ . '/config.php'; $app = defined('APP_NAME') ? APP_NAME : 'Silent Bid Pro'; ?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Privacy Policy — <?php echo htmlspecialchars($app); ?></title>
<style>
 body{font-family:-apple-system,"Avenir Next",system-ui,sans-serif;color:#172235;max-width:720px;margin:0 auto;padding:2rem 1.25rem;line-height:1.6}
 h1{font-family:Georgia,serif} h2{margin-top:1.75rem} a{color:#28785f} .muted{color:#666;font-size:.9rem}
</style></head><body>
<h1>Privacy Policy</h1>
<p class="muted">Last updated: July 5, 2026</p>
<p><?php echo htmlspecialchars($app); ?> ("we," "us") operates a silent-auction fundraising platform for nonprofit organizations. This policy explains what we collect and how we use it. We do not sell your personal information, and we do not track you across other companies' apps or websites.</p>

<h2>Information we collect</h2>
<ul>
<li><strong>Mobile phone number</strong> — used to sign you in by SMS verification code and to send you auction notifications (for example, when you are outbid or win an item).</li>
<li><strong>Name</strong> — shown alongside your bids within an auction.</li>
<li><strong>Bidding activity</strong> — the items you bid on, your bid amounts, and your watchlist, so we can run the auction and show you your status.</li>
</ul>

<h2>How we use it</h2>
<p>Solely to operate the auction: authenticate you, place and track bids, send auction-related notifications, and let event organizers run their fundraiser. We use these categories only for app functionality — never for advertising or cross-app tracking.</p>

<h2>Payments</h2>
<p>Payments for items you win are processed securely by <strong>Stripe</strong> on our website. Card details are handled by Stripe and are never stored on our servers.</p>

<h2>Sharing</h2>
<p>We share the minimum necessary with the nonprofit hosting the auction you participate in (to fulfill your winning items) and with service providers that help us operate (SMS delivery, payment processing). We do not sell your data.</p>

<h2>Data retention &amp; deletion</h2>
<p>You can permanently delete your account at any time from the app (Profile → Delete account) or by contacting us. On deletion we remove your personal information and sign-in credentials; anonymized auction records may be retained where required for the organizer's financial records.</p>

<h2>Contact</h2>
<p>Questions about this policy: <a href="mailto:<?php echo htmlspecialchars($vault_contact_email ?? 'support@silentbidpro.com'); ?>"><?php echo htmlspecialchars($vault_contact_email ?? 'support@silentbidpro.com'); ?></a></p>
<p class="muted"><a href="/terms.php">Terms of Service</a> · <a href="/">Home</a></p>
</body></html>
