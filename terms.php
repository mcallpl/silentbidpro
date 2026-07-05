<?php require_once __DIR__ . '/config.php'; $app = defined('APP_NAME') ? APP_NAME : 'Silent Bid Pro'; ?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Terms of Service — <?php echo htmlspecialchars($app); ?></title>
<style>
 body{font-family:-apple-system,"Avenir Next",system-ui,sans-serif;color:#172235;max-width:720px;margin:0 auto;padding:2rem 1.25rem;line-height:1.6}
 h1{font-family:Georgia,serif} h2{margin-top:1.75rem} a{color:#28785f} .muted{color:#666;font-size:.9rem}
</style></head><body>
<h1>Terms of Service</h1>
<p class="muted">Last updated: July 5, 2026</p>
<p>Welcome to <?php echo htmlspecialchars($app); ?>. By creating an account and bidding, you agree to these terms.</p>

<h2>Bidding</h2>
<p>Bids are binding offers to purchase the item at the bid amount. The highest bid when an auction closes wins. Some items support proxy (maximum) bids, an automatic increment, anti-sniping time extensions, and an optional "Buy It Now" price. Bid amounts and timing are determined by our servers.</p>

<h2>Physical goods &amp; payment</h2>
<p>Auction items are physical goods and experiences provided by the nonprofit hosting the event, fulfilled outside the app. If you win, you complete payment securely on our website (processed by Stripe). 100% of your winning bid goes to the nonprofit; any optional buyer's premium or tip supports the platform and is disclosed at checkout.</p>

<h2>Your account</h2>
<p>You are responsible for activity on your account and for keeping your phone number current. You may delete your account at any time from the app.</p>

<h2>Conduct</h2>
<p>Don't place bids you can't honor, interfere with the auction, or misuse the service. We may suspend accounts that violate these terms.</p>

<h2>No warranty; limitation of liability</h2>
<p>The service is provided "as is." To the extent permitted by law, we are not liable for indirect or incidental damages arising from use of the service. Items are described by the hosting nonprofit; we do not warrant their condition or value.</p>

<h2>Contact</h2>
<p><a href="mailto:<?php echo htmlspecialchars($vault_contact_email ?? 'support@silentbidpro.com'); ?>"><?php echo htmlspecialchars($vault_contact_email ?? 'support@silentbidpro.com'); ?></a></p>
<p class="muted"><a href="/privacy.php">Privacy Policy</a> · <a href="/">Home</a></p>
</body></html>
