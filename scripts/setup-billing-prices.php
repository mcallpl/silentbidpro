<?php
// ============================================================
// SETUP STRIPE BILLING PRICES (SaaS subscriptions)
// Creates (idempotently) a Product + recurring monthly Price for Pro and
// Enterprise on the PLATFORM Stripe account, and prints the Price IDs to add to
// the vault as $vault_stripe_price_pro / $vault_stripe_price_enterprise.
//
// Run once per Stripe mode (test, then live):
//   php scripts/setup-billing-prices.php
// Uses STRIPE_SECRET_KEY. The key must allow Products/Prices write + read.
// ============================================================

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db-helpers.php';
require_once __DIR__ . '/../includes/stripe-utils.php';
require_once __DIR__ . '/../includes/plans.php';

if (!STRIPE_SECRET_KEY) {
    fwrite(STDERR, "STRIPE_SECRET_KEY is not configured.\n");
    exit(1);
}

$plans = [
    'pro'        => ['lookup' => 'sbp_pro_monthly',        'vault' => 'vault_stripe_price_pro'],
    'enterprise' => ['lookup' => 'sbp_enterprise_monthly', 'vault' => 'vault_stripe_price_enterprise'],
];

// Live keys can be sk_live_ (secret) or rk_live_ (restricted).
echo "Setting up Stripe Billing prices (mode: " . (preg_match('/^(sk|rk)_live_/', STRIPE_SECRET_KEY) ? 'LIVE' : 'test') . ")\n\n";

$results = [];
foreach ($plans as $plan => $cfg) {
    $feat = planFeatures($plan);
    $amount = (int)$feat['price_monthly_cents'];
    $name = 'Silent Bid Pro — ' . $feat['label'] . ' plan';

    // Idempotent: reuse an existing price with this lookup_key.
    $existing = callStripeAPI('/v1/prices?lookup_keys[]=' . urlencode($cfg['lookup']) . '&limit=1', [], 'GET');
    if (!empty($existing['data'][0]['id'])) {
        $price_id = $existing['data'][0]['id'];
        echo "✓ {$plan}: reusing existing price {$price_id} (lookup {$cfg['lookup']})\n";
        $results[$cfg['vault']] = $price_id;
        continue;
    }

    // Create Product.
    $product = callStripeAPI('/v1/products', [
        'name' => $name,
        'metadata' => ['plan' => $plan, 'app' => 'silentbidpro'],
    ], 'POST');
    if (empty($product['id'])) {
        fwrite(STDERR, "Failed to create product for {$plan}: " . json_encode($product) . "\n");
        exit(1);
    }

    // Create recurring monthly Price with a stable lookup_key.
    $price = callStripeAPI('/v1/prices', [
        'product'      => $product['id'],
        'unit_amount'  => $amount,
        'currency'     => 'usd',
        'recurring'    => ['interval' => 'month'],
        'lookup_key'   => $cfg['lookup'],
        'metadata'     => ['plan' => $plan],
    ], 'POST');
    if (empty($price['id'])) {
        fwrite(STDERR, "Failed to create price for {$plan}: " . json_encode($price) . "\n");
        exit(1);
    }
    echo "✓ {$plan}: created price {$price['id']} (\${$amount}/100 per month)\n";
    $results[$cfg['vault']] = $price['id'];
}

echo "\nAdd these to /var/www/vault/secrets.php:\n";
foreach ($results as $var => $id) {
    echo "  \${$var} = '{$id}';\n";
}
echo "\nThen set the billing webhook secret as \$vault_stripe_billing_webhook_secret.\n";
