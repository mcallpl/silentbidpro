<?php
// ============================================================
// STRIPE BILLING (SaaS subscriptions) — WEB ONLY.
// Sells Pro/Enterprise via Stripe Checkout (mode=subscription), manages them via
// the Stripe Billing Portal, and drives organizations.plan from Stripe webhook
// events. Never referenced by the iOS app.
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';
require_once __DIR__ . '/stripe-utils.php';
require_once __DIR__ . '/plans.php';

/** Recurring Price ID for a paid plan ('' for free/unknown or unconfigured). */
function billingPriceIdForPlan(string $plan): string {
    switch (normalizePlan($plan)) {
        case 'pro':        return (string)STRIPE_PRICE_PRO;
        case 'enterprise': return (string)STRIPE_PRICE_ENTERPRISE;
        default:           return '';
    }
}

/** Reverse map: a Stripe Price ID -> plan key (null if not one of ours). */
function planForPriceId(?string $price_id): ?string {
    if (!$price_id) return null;
    if (STRIPE_PRICE_PRO && $price_id === STRIPE_PRICE_PRO) return 'pro';
    if (STRIPE_PRICE_ENTERPRISE && $price_id === STRIPE_PRICE_ENTERPRISE) return 'enterprise';
    return null;
}

function orgForSubscription(?string $sub_id): int {
    if (!$sub_id) return 0;
    return (int)dbGetValue("SELECT id FROM organizations WHERE stripe_subscription_id = ?", [$sub_id]);
}
function orgForCustomer(?string $cust_id): int {
    if (!$cust_id) return 0;
    return (int)dbGetValue("SELECT id FROM organizations WHERE stripe_customer_id = ?", [$cust_id]);
}

/** Get or create the org's Stripe Billing customer; returns customer id or ''. */
function getOrCreateOrgBillingCustomer(int $org_id): string {
    $org = dbGetRow("SELECT id, name, contact_email, stripe_customer_id FROM organizations WHERE id = ?", [$org_id]);
    if (!$org) return '';
    if (!empty($org['stripe_customer_id'])) return $org['stripe_customer_id'];

    $data = ['name' => $org['name'], 'metadata' => ['org_id' => $org_id, 'app' => 'silentbidpro']];
    if (!empty($org['contact_email'])) $data['email'] = $org['contact_email'];
    $cust = callStripeAPI('/v1/customers', $data, 'POST');
    if (empty($cust['id'])) return '';

    dbUpdate("UPDATE organizations SET stripe_customer_id = ? WHERE id = ?", [$cust['id'], $org_id]);
    return $cust['id'];
}

/**
 * Create a subscription Checkout Session for an org upgrading to a paid plan.
 * @return array ['success'=>bool, 'url'=>string] | ['success'=>false,'error'=>string]
 */
function createSubscriptionCheckout(int $org_id, string $plan, string $success_url, string $cancel_url): array {
    $plan = normalizePlan($plan);
    if ($plan === 'free') {
        return ['success' => false, 'error' => 'Choose a paid plan to subscribe.'];
    }
    $price_id = billingPriceIdForPlan($plan);
    if (!$price_id) {
        return ['success' => false, 'error' => 'Billing is not configured for the ' . $plan . ' plan yet.'];
    }
    $customer = getOrCreateOrgBillingCustomer($org_id);
    if (!$customer) {
        return ['success' => false, 'error' => 'Could not create a billing customer for this organization.'];
    }

    $session = callStripeAPI('/v1/checkout/sessions', [
        'mode'                 => 'subscription',
        'customer'             => $customer,
        'line_items'           => [['price' => $price_id, 'quantity' => 1]],
        'success_url'          => $success_url,
        'cancel_url'           => $cancel_url,
        'client_reference_id'  => (string)$org_id,
        'metadata'             => ['org_id' => $org_id, 'plan' => $plan],
        // Stamp the SUBSCRIPTION too, so later subscription.* events resolve the org.
        'subscription_data'    => ['metadata' => ['org_id' => $org_id, 'plan' => $plan]],
        'allow_promotion_codes'=> 'true',
    ], 'POST');

    if (empty($session['url'])) {
        error_log('[BILLING] Checkout session failed: ' . json_encode($session));
        return ['success' => false, 'error' => $session['error']['message'] ?? 'Could not start checkout.'];
    }
    return ['success' => true, 'url' => $session['url'], 'session_id' => $session['id'] ?? null];
}

/** Create a Stripe Billing Portal session so an org can manage/cancel its plan. */
function createBillingPortalSession(int $org_id, string $return_url): array {
    $customer = (string)dbGetValue("SELECT stripe_customer_id FROM organizations WHERE id = ?", [$org_id]);
    if (!$customer) {
        return ['success' => false, 'error' => 'No subscription on file for this organization.'];
    }
    $session = callStripeAPI('/v1/billing_portal/sessions', [
        'customer'   => $customer,
        'return_url' => $return_url,
    ], 'POST');
    if (empty($session['url'])) {
        return ['success' => false, 'error' => 'Could not open the billing portal.'];
    }
    return ['success' => true, 'url' => $session['url']];
}

/**
 * Apply a Stripe billing webhook event to organizations.plan/subscription state.
 * Pure DB (no Stripe calls) so it's unit-testable with mock event payloads.
 * @return bool whether the event was handled
 */
function applySubscriptionEvent(array $event): bool {
    $type = $event['type'] ?? '';
    $obj  = $event['data']['object'] ?? [];

    switch ($type) {
        case 'checkout.session.completed':
            if (($obj['mode'] ?? '') !== 'subscription') return false; // not a subscription checkout
            $org_id = (int)($obj['metadata']['org_id'] ?? $obj['client_reference_id'] ?? 0);
            $plan   = normalizePlan($obj['metadata']['plan'] ?? 'free');
            if (!$org_id || $plan === 'free') return false;
            dbUpdate(
                "UPDATE organizations
                 SET plan = ?, stripe_customer_id = ?, stripe_subscription_id = ?, subscription_status = 'active'
                 WHERE id = ?",
                [$plan, $obj['customer'] ?? null, $obj['subscription'] ?? null, $org_id]
            );
            return true;

        case 'customer.subscription.created':
        case 'customer.subscription.updated':
            $sub_id   = $obj['id'] ?? null;
            $customer = $obj['customer'] ?? null;
            $status   = $obj['status'] ?? 'active';
            $price_id = $obj['items']['data'][0]['price']['id'] ?? null;
            $plan     = planForPriceId($price_id);
            $meta_org = (int)($obj['metadata']['org_id'] ?? 0);

            $org_id = orgForSubscription($sub_id) ?: ($meta_org ?: orgForCustomer($customer));
            if (!$org_id) return false;

            // Active/trialing -> apply the plan; a non-live status keeps the current
            // plan but records the status (past_due/unpaid/paused).
            $apply_plan = in_array($status, ['active', 'trialing'], true) && $plan;
            if ($apply_plan) {
                dbUpdate(
                    "UPDATE organizations
                     SET plan = ?, stripe_customer_id = ?, stripe_subscription_id = ?, subscription_status = ?
                     WHERE id = ?",
                    [$plan, $customer, $sub_id, $status, $org_id]
                );
            } else {
                dbUpdate(
                    "UPDATE organizations
                     SET stripe_customer_id = ?, stripe_subscription_id = ?, subscription_status = ?
                     WHERE id = ?",
                    [$customer, $sub_id, $status, $org_id]
                );
            }
            return true;

        case 'customer.subscription.deleted':
            $org_id = orgForSubscription($obj['id'] ?? null) ?: orgForCustomer($obj['customer'] ?? null);
            if (!$org_id) return false;
            // Subscription ended -> back to Free.
            dbUpdate(
                "UPDATE organizations
                 SET plan = 'free', subscription_status = 'canceled', stripe_subscription_id = NULL
                 WHERE id = ?",
                [$org_id]
            );
            return true;

        case 'invoice.payment_failed':
            $org_id = orgForCustomer($obj['customer'] ?? null);
            if (!$org_id) return false;
            dbUpdate("UPDATE organizations SET subscription_status = 'past_due' WHERE id = ?", [$org_id]);
            return true;

        case 'invoice.paid':
            $org_id = orgForCustomer($obj['customer'] ?? null);
            if (!$org_id) return false;
            dbUpdate("UPDATE organizations SET subscription_status = 'active' WHERE id = ?", [$org_id]);
            return true;
    }
    return false;
}
