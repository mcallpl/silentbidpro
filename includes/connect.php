<?php
// ============================================================
// STRIPE CONNECT (Express) — organizer payout accounts
//
// The world-class path: an organizer clicks "Connect with Stripe",
// Stripe's own hosted onboarding collects their identity + bank
// account, and every payment for their events then settles straight
// to their bank. No API keys, nothing to paste.
//
// Precedence for where an event's money goes:
//   1. Per-event BYO API keys (event_settings)  — explicit override
//   2. Org's Connect account (charges_enabled)  — this file
//   3. Platform Stripe account                  — fallback
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';
require_once __DIR__ . '/stripe-utils.php';

/**
 * Is Connect enabled on the PLATFORM Stripe account?
 * Cheap read-only probe (listing connected accounts), cached per request.
 * Until the owner activates Connect at dashboard.stripe.com/connect this
 * returns false and every Connect feature degrades gracefully.
 */
function connectPlatformAvailable(): bool {
    static $available = null;
    if ($available !== null) return $available;
    $r = callStripeAPI('/v1/accounts?limit=1', [], 'GET');
    $available = !isset($r['error']);
    return $available;
}

/** The org's Connect columns (migration 009). */
function orgConnectAccount(int $org_id): ?array {
    if (!$org_id) return null;
    $row = dbGetRow(
        "SELECT stripe_account_id, stripe_charges_enabled, stripe_payouts_enabled, stripe_onboarding_status
         FROM organizations WHERE id = ?", [$org_id]);
    return $row ?: null;
}

/** True when the org's Connect account can take charges. */
function orgConnectReady(int $org_id): bool {
    $a = orgConnectAccount($org_id);
    return $a && !empty($a['stripe_account_id']) && (int)$a['stripe_charges_enabled'] === 1;
}

/**
 * Where should THIS event's money settle via Connect?
 * Returns the connected acct_… id, or null (BYO keys set, or org not ready).
 */
function eventConnectDestination(int $event_id): ?string {
    if (!$event_id) return null;
    // Explicit per-event keys always win — that event runs on its own account.
    $own = dbGetRow(
        "SELECT stripe_key_publishable, stripe_key_secret FROM event_settings WHERE event_id = ?",
        [$event_id]);
    if ($own && !empty($own['stripe_key_publishable']) && !empty($own['stripe_key_secret'])) {
        return null;
    }
    $org_id = (int)dbGetValue("SELECT organization_id FROM events WHERE id = ?", [$event_id]);
    if (!$org_id || !orgConnectReady($org_id)) return null;
    $a = orgConnectAccount($org_id);
    return $a['stripe_account_id'];
}

/**
 * Apply Connect routing to a payment payload (PaymentIntent params, or the
 * payment_intent_data of a Checkout Session). on_behalf_of makes the
 * connected account the settlement merchant — Stripe's fee comes out of
 * their side and the full remainder lands in their balance/bank.
 */
function connectPaymentParams(?string $destination): array {
    if (!$destination) return [];
    return [
        'on_behalf_of' => $destination,
        'transfer_data' => ['destination' => $destination],
    ];
}

/**
 * Pull the live account state from Stripe and persist it on the org.
 * Returns the updated status row (or null when the org has no account).
 */
function refreshOrgConnectStatus(int $org_id): ?array {
    $a = orgConnectAccount($org_id);
    if (!$a || empty($a['stripe_account_id'])) return null;
    $acct = callStripeAPI('/v1/accounts/' . urlencode($a['stripe_account_id']), [], 'GET');
    if (empty($acct['id'])) return $a; // transient API failure: keep stored state
    $charges = !empty($acct['charges_enabled']) ? 1 : 0;
    $payouts = !empty($acct['payouts_enabled']) ? 1 : 0;
    $disabled = $acct['requirements']['disabled_reason'] ?? null;
    $due      = $acct['requirements']['currently_due'] ?? [];
    if ($charges && $payouts) { $status = 'complete'; }
    elseif ($disabled)        { $status = 'restricted'; }
    else                      { $status = 'pending'; }
    dbUpdate(
        "UPDATE organizations
         SET stripe_charges_enabled = ?, stripe_payouts_enabled = ?, stripe_onboarding_status = ?
         WHERE id = ?",
        [$charges, $payouts, $status, $org_id]);
    return [
        'stripe_account_id' => $acct['id'],
        'stripe_charges_enabled' => $charges,
        'stripe_payouts_enabled' => $payouts,
        'stripe_onboarding_status' => $status,
        'requirements_due' => count($due),
    ];
}

/**
 * Start (or resume) Express onboarding for an org.
 * Creates the Express account on first call, then a fresh account_link.
 * Returns ['success'=>true,'url'=>…] or ['success'=>false,'error'=>…].
 */
function startConnectOnboarding(int $org_id, string $return_url, string $refresh_url): array {
    if (!connectPlatformAvailable()) {
        return ['success' => false, 'error' => 'Stripe Connect is not activated on the platform account yet.'];
    }
    $org = dbGetRow("SELECT id, name, contact_email, stripe_account_id FROM organizations WHERE id = ?", [$org_id]);
    if (!$org) return ['success' => false, 'error' => 'Organization not found'];

    $acct_id = $org['stripe_account_id'];
    if (!$acct_id) {
        $params = [
            'type' => 'express',
            'metadata' => ['org_id' => (string)$org_id, 'app' => 'silentbidpro'],
            'capabilities' => [
                'card_payments' => ['requested' => 'true'],
                'transfers'     => ['requested' => 'true'],
            ],
        ];
        if (!empty($org['contact_email'])) $params['email'] = $org['contact_email'];
        $acct = callStripeAPI('/v1/accounts', $params, 'POST');
        if (empty($acct['id'])) {
            return ['success' => false, 'error' => $acct['error']['message'] ?? 'Could not create the Stripe account'];
        }
        $acct_id = $acct['id'];
        dbUpdate(
            "UPDATE organizations SET stripe_account_id = ?, stripe_onboarding_status = 'pending' WHERE id = ?",
            [$acct_id, $org_id]);
    }

    $link = callStripeAPI('/v1/account_links', [
        'account' => $acct_id,
        'type' => 'account_onboarding',
        'return_url' => $return_url,
        'refresh_url' => $refresh_url,
    ], 'POST');
    if (empty($link['url'])) {
        return ['success' => false, 'error' => $link['error']['message'] ?? 'Could not start Stripe onboarding'];
    }
    return ['success' => true, 'url' => $link['url'], 'account_id' => $acct_id];
}

/**
 * One-click link into the connected account's own Stripe Express dashboard
 * (balance, payouts, bank details) — no Stripe login juggling for the org.
 */
function connectDashboardLink(int $org_id): ?string {
    $a = orgConnectAccount($org_id);
    if (!$a || empty($a['stripe_account_id'])) return null;
    $r = callStripeAPI('/v1/accounts/' . urlencode($a['stripe_account_id']) . '/login_links', [], 'POST');
    return $r['url'] ?? null;
}
