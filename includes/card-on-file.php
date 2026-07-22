<?php
// ============================================================
// CARD ON FILE + AUTO-CHARGE
//
// Bidders save a card (Stripe Checkout in setup mode) before their
// first bid; when an auction fully closes, each winner's saved card
// is charged ONE combined off-session PaymentIntent for everything
// they won. Falls back to the classic checkout link when a card is
// missing or declined. All behavior is per-event (require_card_on_bid,
// auto_charge_on_close) — nothing is client-specific.
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';
require_once __DIR__ . '/stripe-utils.php';
require_once __DIR__ . '/notifications.php';

/**
 * True when this user is the App Store review / demo account.
 * The demo user must keep working without a real card.
 */
function isDemoUser($user) {
    if (!defined('DEMO_LOGIN_PHONE') || DEMO_LOGIN_PHONE === '') {
        return false;
    }
    $phone = is_array($user) ? ($user['phone_number'] ?? '') : '';
    return $phone !== '' && $phone === DEMO_LOGIN_PHONE;
}

/**
 * Does the user have a saved payment method?
 */
function userHasSavedCard($user) {
    if (is_array($user)) {
        if (array_key_exists('stripe_payment_method_id', $user)) {
            return !empty($user['stripe_payment_method_id']);
        }
        $user = (int)($user['id'] ?? 0);
    }
    return !empty(dbGetValue(
        "SELECT stripe_payment_method_id FROM users WHERE id = ?",
        [(int)$user]
    ));
}

/**
 * Must this user save a card before bidding in this event?
 * @return bool true when a card is REQUIRED and MISSING
 */
function cardRequiredButMissing($user, $event_id) {
    if (isDemoUser($user)) {
        return false;
    }
    $required = dbGetValue(
        "SELECT require_card_on_bid FROM events WHERE id = ?",
        [(int)$event_id]
    );
    // Unknown event or column missing → don't block bidding.
    if ($required === null || (int)$required !== 1) {
        return false;
    }
    return !userHasSavedCard($user);
}

/**
 * Create a Stripe Checkout Session in setup mode to save a card.
 * The card is only charged if the bidder wins.
 *
 * @param array $user Current user row
 * @param int $event_id Event the bidder is in (keys + attribution)
 * @param string $return_path Internal path to return to after saving
 * @return array ['success'=>bool, 'url'=>string] or ['success'=>false,'error'=>...]
 */
function createCardSetupSession($user, $event_id, $return_path = 'items.php') {
    $keys = getEventStripeKeys($event_id);
    if (empty($keys['secret_key'])) {
        return ['success' => false, 'error' => 'Payment configuration unavailable'];
    }

    $customer = getOrCreateStripeCustomer((int)$user['id'], $user['email'] ?? '', $keys['secret_key']);
    if (!$customer || empty($customer['id'])) {
        return ['success' => false, 'error' => 'Could not create payment profile'];
    }

    // Only allow returning to an internal relative path.
    $return_path = ltrim((string)$return_path, '/');
    if ($return_path === '' || preg_match('#^(https?:)?//#i', $return_path) || strpos($return_path, '..') !== false) {
        $return_path = 'items.php';
    }

    $session_data = [
        'mode' => 'setup',
        'payment_method_types[0]' => 'card',
        'customer' => $customer['id'],
        'success_url' => APP_DOMAIN . '/card-saved.php?session_id={CHECKOUT_SESSION_ID}&return=' . urlencode($return_path),
        'cancel_url' => APP_DOMAIN . '/' . $return_path,
        'metadata[purpose]' => 'card_setup',
        'metadata[user_id]' => (int)$user['id'],
        'metadata[event_id]' => (int)$event_id
    ];

    $response = callStripeAPI('/v1/checkout/sessions', $session_data, 'POST', $keys['secret_key']);
    if (empty($response['id']) || empty($response['url'])) {
        $error = $response['error']['message'] ?? 'Failed to start card setup';
        return ['success' => false, 'error' => $error];
    }

    return ['success' => true, 'url' => $response['url'], 'session_id' => $response['id']];
}

/**
 * Persist the saved payment method from a completed setup-mode Checkout
 * Session onto the user row. Idempotent — safe to call from both the
 * return redirect and the webhook.
 *
 * @param string|array $session Session id or full session object
 * @return bool
 */
function saveCardFromSetupSession($session, $secret_key = '') {
    $secret_key = $secret_key ?: STRIPE_SECRET_KEY;

    if (is_string($session)) {
        $session = callStripeAPI('/v1/checkout/sessions/' . urlencode($session), [], 'GET', $secret_key);
    }
    if (empty($session['id']) || ($session['mode'] ?? '') !== 'setup') {
        return false;
    }

    $user_id = (int)($session['metadata']['user_id'] ?? 0);
    $setup_intent_id = is_array($session['setup_intent'] ?? null)
        ? ($session['setup_intent']['id'] ?? '')
        : ($session['setup_intent'] ?? '');
    if (!$user_id || !$setup_intent_id) {
        return false;
    }

    $si = callStripeAPI('/v1/setup_intents/' . urlencode($setup_intent_id), [], 'GET', $secret_key);
    $pm_id = is_array($si['payment_method'] ?? null)
        ? ($si['payment_method']['id'] ?? '')
        : ($si['payment_method'] ?? '');
    if (($si['status'] ?? '') !== 'succeeded' || !$pm_id) {
        return false;
    }

    $brand = '';
    $last4 = '';
    $pm = callStripeAPI('/v1/payment_methods/' . urlencode($pm_id), [], 'GET', $secret_key);
    if (!empty($pm['card'])) {
        $brand = (string)($pm['card']['brand'] ?? '');
        $last4 = (string)($pm['card']['last4'] ?? '');
    }

    dbUpdate(
        "UPDATE users SET stripe_payment_method_id = ?, card_brand = ?, card_last4 = ? WHERE id = ?",
        [$pm_id, $brand, $last4, $user_id]
    );

    dbInsert(
        "INSERT INTO audit_log (event_type, user_id, description, created_at)
         VALUES (?, ?, ?, NOW())",
        ['CARD_SAVED', $user_id, 'Payment method saved for bidding (' . $brand . ' •••• ' . $last4 . ')']
    );

    return true;
}

/**
 * Unpaid (pending) won-item transactions for a user, optionally scoped
 * to one event. Only rows not already claimed by a live manual checkout
 * and not exhausted by prior auto-charge attempts.
 */
function getAutoChargeableTransactions($user_id, $event_id = 0) {
    $sql = "SELECT t.id, t.item_id, t.amount, i.title, i.event_id
            FROM transactions t
            JOIN items i ON i.id = t.item_id
            WHERE t.user_id = ?
              AND t.status = 'pending'
              AND t.stripe_checkout_session_id IS NULL
              AND t.auto_charge_attempts < 3
              AND i.is_closed = 1
              AND i.current_high_bidder_id = t.user_id";
    $params = [(int)$user_id];
    if ($event_id) {
        $sql .= " AND i.event_id = ?";
        $params[] = (int)$event_id;
    }
    return dbGetAll($sql . " ORDER BY t.id ASC", $params);
}

/**
 * Charge ONE combined off-session PaymentIntent for everything the
 * winner owes in this event, using their saved card. Sends a receipt
 * SMS on success; on a hard decline, stops retrying and sends the
 * classic pay link instead.
 *
 * @return array ['charged'=>bool, 'amount'=>float, 'error'=>string|null]
 */
function autoChargeWinner($user_id, $event_id) {
    $user = dbGetRow(
        "SELECT id, phone_number, full_name, email, stripe_customer_id,
                stripe_payment_method_id, card_brand, card_last4
         FROM users WHERE id = ?",
        [(int)$user_id]
    );
    if (!$user || empty($user['stripe_payment_method_id']) || empty($user['stripe_customer_id'])) {
        return ['charged' => false, 'amount' => 0, 'hard_declined' => false, 'error' => 'no saved card'];
    }

    $rows = getAutoChargeableTransactions($user_id, $event_id);
    if (!$rows) {
        return ['charged' => false, 'amount' => 0, 'hard_declined' => false, 'error' => null];
    }

    $attribution = dbGetRow(
        "SELECT o.name AS organization_name, e.name AS event_name, e.pickup_instructions
         FROM events e JOIN organizations o ON o.id = e.organization_id
         WHERE e.id = ?",
        [(int)$event_id]
    );
    $org_name = $attribution['organization_name'] ?? '';

    // TEST MODE: $1 per item instead of the real bids; each transaction
    // record stays in sync with what was actually charged.
    $test_mode = defined('TEST_CHARGE_DOLLAR') && TEST_CHARGE_DOLLAR;
    $total_cents = 0;
    $titles = [];
    $tx_ids = [];
    foreach ($rows as $r) {
        $line = $test_mode ? 1.00 : (float)$r['amount'];
        $total_cents += (int)round($line * 100);
        $titles[] = $r['title'];
        $tx_ids[] = (int)$r['id'];
    }
    if ($total_cents < 50) { // Stripe minimum charge
        return ['charged' => false, 'amount' => 0, 'hard_declined' => false, 'error' => 'total below Stripe minimum'];
    }

    // Claim the rows (bump attempts) BEFORE the network call so an
    // overlapping run can't double-charge the same set.
    $placeholders = implode(',', array_fill(0, count($tx_ids), '?'));
    $claimed = dbUpdate(
        "UPDATE transactions SET auto_charge_attempts = auto_charge_attempts + 1
         WHERE id IN ({$placeholders}) AND status = 'pending' AND stripe_checkout_session_id IS NULL",
        $tx_ids
    );
    if ((int)$claimed !== count($tx_ids)) {
        return ['charged' => false, 'amount' => 0, 'hard_declined' => false, 'error' => 'rows changed underneath; skipped'];
    }

    $keys = getEventStripeKeys($event_id);
    $description = ($org_name !== '' ? $org_name . ' — ' : '')
        . 'Silent auction: ' . count($rows) . ' item(s)'
        . ($test_mode ? ' (test charge)' : '');

    $pi_data = [
        'amount' => $total_cents,
        'currency' => 'usd',
        'customer' => $user['stripe_customer_id'],
        'payment_method' => $user['stripe_payment_method_id'],
        'off_session' => 'true',
        'confirm' => 'true',
        'description' => $description,
        'metadata[purpose]' => 'auction_auto_charge',
        'metadata[user_id]' => (int)$user_id,
        'metadata[event_id]' => (int)$event_id,
        'metadata[transaction_ids]' => implode(',', $tx_ids),
        'metadata[organization]' => $org_name
    ];

    // Connect routing: auto-charges settle straight to the org's connected
    // Stripe account when configured (no-op for BYO-key events).
    require_once __DIR__ . '/connect.php';
    $connect_dest = eventConnectDestination((int)$event_id);
    if ($connect_dest) {
        $pi_data['on_behalf_of'] = $connect_dest;
        $pi_data['transfer_data[destination]'] = $connect_dest;
    }

    // Idempotency: same winner + same set of transactions + same attempt
    // count can never create two live charges.
    $attempt = (int)dbGetValue("SELECT MAX(auto_charge_attempts) FROM transactions WHERE id = ?", [$tx_ids[0]]);
    $idem_key = 'autochg-' . $event_id . '-' . $user_id . '-' . md5(implode(',', $tx_ids)) . '-a' . $attempt;

    $pi = callStripeAPI('/v1/payment_intents', $pi_data, 'POST', $keys['secret_key'], ['Idempotency-Key: ' . $idem_key]);

    if (!empty($pi['id']) && ($pi['status'] ?? '') === 'succeeded') {
        foreach ($rows as $r) {
            dbUpdate(
                "UPDATE transactions SET status = 'paid', stripe_payment_intent_id = ?, amount = ? WHERE id = ?",
                [$pi['id'], $test_mode ? 1.00 : (float)$r['amount'], (int)$r['id']]
            );
        }
        dbInsert(
            "INSERT INTO audit_log (event_type, user_id, description, created_at)
             VALUES (?, ?, ?, NOW())",
            ['AUTO_CHARGE_SUCCEEDED', (int)$user_id,
             'Auto-charged $' . number_format($total_cents / 100, 2) . ' for ' . count($rows) . ' item(s): ' . $pi['id']]
        );

        // Receipt SMS with what happens next.
        if (!empty($user['phone_number'])) {
            $item_list = count($titles) === 1
                ? '"' . $titles[0] . '"'
                : count($titles) . ' items';
            $msg = ($org_name !== '' ? $org_name . ': ' : '')
                . 'You won ' . $item_list . '! Your card ending ' . ($user['card_last4'] ?: '••••')
                . ' was charged $' . number_format($total_cents / 100, 2)
                . '. Receipt & pickup details: ' . rtrim(APP_DOMAIN, '/') . '/my-bids.php';
            sendTwilioSMS($user['phone_number'], $msg, (int)$user_id);
        }

        // Emailed receipt/thank-you (no-op when the user has no email).
        try {
            require_once __DIR__ . '/mailer.php';
            sendPurchaseReceiptEmail((int)$user_id, $tx_ids);
        } catch (\Throwable $e) {
            error_log('[AUTO-CHARGE] receipt email failed (charge stands): ' . $e->getMessage());
        }

        return ['charged' => true, 'amount' => $total_cents / 100, 'hard_declined' => false, 'error' => null];
    }

    // Failure. Card errors are final — stop retrying and send the pay link.
    $err_type = $pi['error']['type'] ?? '';
    $err_msg = $pi['error']['message'] ?? 'charge failed';
    $hard_decline = ($err_type === 'card_error' || $err_type === 'invalid_request_error');

    if ($hard_decline) {
        dbUpdate(
            "UPDATE transactions SET auto_charge_attempts = 3, auto_charge_last_error = ?
             WHERE id IN ({$placeholders}) AND status = 'pending'",
            array_merge([substr($err_msg, 0, 250)], $tx_ids)
        );
        if (!empty($user['phone_number'])) {
            $pay_url = rtrim(APP_DOMAIN, '/') . '/checkout.php?all=1&event=' . (int)$event_id;
            $msg = ($org_name !== '' ? $org_name . ': ' : '')
                . 'You won ' . count($titles) . ' item(s), but your saved card could not be charged. '
                . 'Please pay here: ' . $pay_url;
            sendTwilioSMS($user['phone_number'], $msg, (int)$user_id);
        }
    } else {
        dbUpdate(
            "UPDATE transactions SET auto_charge_last_error = ? WHERE id IN ({$placeholders})",
            array_merge([substr($err_msg, 0, 250)], $tx_ids)
        );
    }

    dbInsert(
        "INSERT INTO audit_log (event_type, user_id, description, created_at)
         VALUES (?, ?, ?, NOW())",
        ['AUTO_CHARGE_FAILED', (int)$user_id, substr($err_type . ': ' . $err_msg, 0, 250)]
    );
    error_log('[AUTO-CHARGE] user ' . $user_id . ' event ' . $event_id . ' failed: ' . $err_type . ' ' . $err_msg);

    return ['charged' => false, 'amount' => 0, 'hard_declined' => $hard_decline, 'error' => $err_msg];
}

/**
 * Auto-charge every eligible winner of an event once ALL of its items
 * are closed. Safe to call every cron tick — it no-ops when there is
 * nothing chargeable. Returns a per-user result summary.
 */
function autoChargeEventWinners($event_id) {
    $results = [];

    $event = dbGetRow(
        "SELECT id, auto_charge_on_close FROM events WHERE id = ?",
        [(int)$event_id]
    );
    if (!$event || (int)$event['auto_charge_on_close'] !== 1) {
        return $results;
    }

    // Wait until the WHOLE event is done so each winner gets one combined
    // charge (anti-sniping can keep individual items open a little longer).
    $open_items = (int)dbGetValue(
        "SELECT COUNT(*) FROM items WHERE event_id = ? AND is_closed = 0",
        [(int)$event_id]
    );
    if ($open_items > 0) {
        return $results;
    }

    $winners = dbGetAll(
        "SELECT DISTINCT t.user_id
         FROM transactions t
         JOIN items i ON i.id = t.item_id
         JOIN users u ON u.id = t.user_id
         WHERE i.event_id = ?
           AND t.status = 'pending'
           AND t.stripe_checkout_session_id IS NULL
           AND t.auto_charge_attempts < 3
           AND u.stripe_payment_method_id IS NOT NULL
           AND u.stripe_customer_id IS NOT NULL",
        [(int)$event_id]
    );

    foreach ($winners as $w) {
        $results[(int)$w['user_id']] = autoChargeWinner((int)$w['user_id'], (int)$event_id);
    }

    return $results;
}

?>
