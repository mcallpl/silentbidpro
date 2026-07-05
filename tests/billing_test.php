<?php
// ============================================================
// BILLING WEBHOOK LOGIC TESTS — applySubscriptionEvent() with mock Stripe events.
// Run: php tests/billing_test.php
// ============================================================

// Inject fake recurring Price IDs before config defines the constants.
putenv('STRIPE_PRICE_PRO=price_test_pro');
putenv('STRIPE_PRICE_ENTERPRISE=price_test_ent');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db-helpers.php';
require_once __DIR__ . '/../includes/billing.php';

$pass = 0; $fail = 0; $failures = [];
function check($c, $m) { global $pass,$fail,$failures; if ($c) $pass++; else { $fail++; $failures[]=$m; } }

check(billingPriceIdForPlan('pro') === 'price_test_pro', 'price id for pro');
check(planForPriceId('price_test_ent') === 'enterprise', 'plan for enterprise price');
check(planForPriceId('price_unknown') === null, 'unknown price -> null');

$db = getDB();
$db->begin_transaction();

$org_id = dbInsert("INSERT INTO organizations (name, slug, plan, created_at) VALUES (?,?, 'free', NOW())",
                   ['Billing Test Org', 'billing-test-'.bin2hex(random_bytes(3))]);
function plan_of($id){ return dbGetValue("SELECT plan FROM organizations WHERE id=?", [$id]); }
function status_of($id){ return dbGetValue("SELECT subscription_status FROM organizations WHERE id=?", [$id]); }

// 1) Subscription checkout completed -> Pro, active.
check(applySubscriptionEvent([
    'type' => 'checkout.session.completed',
    'data' => ['object' => [
        'mode' => 'subscription', 'customer' => 'cus_1', 'subscription' => 'sub_1',
        'metadata' => ['org_id' => $org_id, 'plan' => 'pro'],
    ]],
]) === true, 'checkout.session.completed handled');
check(plan_of($org_id) === 'pro', 'org upgraded to pro');
check(status_of($org_id) === 'active', 'status active');

// 2) A one-time PAYMENT checkout (auction item) must be IGNORED by billing.
check(applySubscriptionEvent([
    'type' => 'checkout.session.completed',
    'data' => ['object' => ['mode' => 'payment', 'metadata' => ['item_id' => 5]]],
]) === false, 'payment-mode checkout ignored by billing');
check(plan_of($org_id) === 'pro', 'plan unchanged by payment-mode event');

// 3) Subscription updated to Enterprise price.
check(applySubscriptionEvent([
    'type' => 'customer.subscription.updated',
    'data' => ['object' => [
        'id' => 'sub_1', 'customer' => 'cus_1', 'status' => 'active',
        'items' => ['data' => [['price' => ['id' => 'price_test_ent']]]],
        'metadata' => ['org_id' => $org_id],
    ]],
]) === true, 'subscription.updated handled');
check(plan_of($org_id) === 'enterprise', 'org now enterprise');

// 4) Payment failed -> past_due (plan retained).
applySubscriptionEvent(['type' => 'invoice.payment_failed', 'data' => ['object' => ['customer' => 'cus_1']]]);
check(status_of($org_id) === 'past_due', 'status past_due after failed payment');
check(plan_of($org_id) === 'enterprise', 'plan retained while past_due');

// 5) Invoice paid -> active again.
applySubscriptionEvent(['type' => 'invoice.paid', 'data' => ['object' => ['customer' => 'cus_1']]]);
check(status_of($org_id) === 'active', 'status recovered to active');

// 6) Subscription canceled -> back to Free.
check(applySubscriptionEvent([
    'type' => 'customer.subscription.deleted',
    'data' => ['object' => ['id' => 'sub_1', 'customer' => 'cus_1']],
]) === true, 'subscription.deleted handled');
check(plan_of($org_id) === 'free', 'downgraded to free on cancel');
check(status_of($org_id) === 'canceled', 'status canceled');

// 7) A past_due status update does NOT apply a plan change.
dbUpdate("UPDATE organizations SET plan='pro', stripe_subscription_id='sub_2', stripe_customer_id='cus_2' WHERE id=?", [$org_id]);
applySubscriptionEvent([
    'type' => 'customer.subscription.updated',
    'data' => ['object' => [
        'id' => 'sub_2', 'customer' => 'cus_2', 'status' => 'past_due',
        'items' => ['data' => [['price' => ['id' => 'price_test_ent']]]],
    ]],
]);
check(plan_of($org_id) === 'pro', 'past_due update keeps current plan (no silent upgrade)');
check(status_of($org_id) === 'past_due', 'past_due recorded');

$db->rollback();

echo "PASS: $pass   FAIL: $fail\n";
if ($fail) { echo "\nFAILURES:\n - ".implode("\n - ", $failures)."\n"; exit(1); }
echo "✅ Billing subscription lifecycle drives org plan correctly.\n";
