<?php
// ============================================================
// STRIPE UTILITIES
// Payment processing and checkout session management
// Uses direct Stripe API calls (no SDK required)
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';
require_once __DIR__ . '/payment-requests.php';

/**
 * Get Stripe API keys for an event (event-specific or global fallback)
 * @param int $event_id Event ID
 * @return array ['public_key' => string, 'secret_key' => string]
 */
function getEventStripeKeys($event_id) {
    if (!$event_id) {
        // Fall back to global keys if no event specified
        return [
            'public_key' => STRIPE_PUBLISHABLE_KEY,
            'secret_key' => STRIPE_SECRET_KEY
        ];
    }

    // Get event-specific Stripe keys if configured
    $event_settings = dbGetRow(
        "SELECT stripe_key_publishable, stripe_key_secret FROM event_settings WHERE event_id = ?",
        [(int)$event_id]
    );

    if ($event_settings && !empty($event_settings['stripe_key_publishable']) && !empty($event_settings['stripe_key_secret'])) {
        return [
            'public_key' => $event_settings['stripe_key_publishable'],
            'secret_key' => $event_settings['stripe_key_secret']
        ];
    }

    // Fall back to global keys
    return [
        'public_key' => STRIPE_PUBLISHABLE_KEY,
        'secret_key' => STRIPE_SECRET_KEY
    ];
}

/**
 * Create a Stripe Checkout Session for a winning bid
 * @param int $item_id Item ID
 * @param int $user_id User ID
 * @param float $amount Winning bid amount
 * @param string $item_title Item title
 * @param string $user_email User email (optional)
 * @return array ['success' => bool, 'session_id' => string or 'error' => string]
 */
function createCheckoutSession($item_id, $user_id, $amount, $item_title, $user_email = '') {
    try {
        // Get item and event information
        $item = dbGetRow(
            "SELECT id, event_id FROM items WHERE id = ?",
            [(int)$item_id]
        );

        if (!$item) {
            return ['success' => false, 'error' => 'Item not found'];
        }

        $event_id = (int)($item['event_id'] ?? 0);

        // Attribution: name the source org/event so the Stripe checkout page,
        // receipt, and dashboard clearly show where the money came from
        // (e.g. "Ryan's Reach Foundation" vs "PeopleStar Enterprises").
        $attribution = dbGetRow(
            "SELECT o.name AS organization_name, e.name AS event_name
             FROM events e JOIN organizations o ON o.id = e.organization_id
             WHERE e.id = ?",
            [$event_id]
        );
        $org_name = $attribution['organization_name'] ?? '';
        $event_name = $attribution['event_name'] ?? '';
        $line_description = ($org_name !== '' ? $org_name . ' — ' : '')
            . 'Silent Auction Item #' . $item_id;

        // TEST MODE: when TEST_CHARGE_DOLLAR is enabled (typically in the test
        // server's config.local.php), the bidder completes the full purchase flow
        // but is only ever charged $1, regardless of the winning bid. The Stripe
        // charge and the recorded transaction use the same amount so they stay in
        // sync. Leave this undefined in real production to charge the real amount.
        $charge_amount = $amount;
        if (defined('TEST_CHARGE_DOLLAR') && TEST_CHARGE_DOLLAR) {
            $charge_amount = 1.00;
            $line_description .= ' (test charge — $1)';
        }

        // Get event-specific Stripe keys (or fall back to global)
        $stripe_keys = getEventStripeKeys($event_id);
        $public_key = $stripe_keys['public_key'];
        $secret_key = $stripe_keys['secret_key'];

        if (!$public_key || !$secret_key) {
            return ['success' => false, 'error' => 'Stripe configuration not available'];
        }

        $payment_request = ensurePendingPaymentRequest($item_id, $user_id, $charge_amount);
        if (!$payment_request['success']) {
            return ['success' => false, 'error' => 'Failed to create payment request'];
        }

        if ($payment_request['already_paid']) {
            return ['success' => false, 'error' => 'This item has already been paid'];
        }

        $transaction_id = (int)($payment_request['transaction']['id'] ?? 0);
        if (!$transaction_id) {
            return ['success' => false, 'error' => 'Payment request is missing'];
        }

        // Get or create Stripe customer
        $customer = getOrCreateStripeCustomer($user_id, $user_email, $secret_key);
        if (!$customer || empty($customer['id'])) {
            return ['success' => false, 'error' => 'Failed to create payment customer'];
        }

        // Prepare checkout session data
        $session_data = [
            'payment_method_types[0]' => 'card',
            'customer' => $customer['id'],
            'line_items[0][price_data][currency]' => 'usd',
            'line_items[0][price_data][product_data][name]' => $item_title,
            'line_items[0][price_data][product_data][description]' => $line_description,
            'line_items[0][price_data][unit_amount]' => (int)round($charge_amount * 100),
            'line_items[0][quantity]' => '1',
            'mode' => 'payment',
            'success_url' => APP_DOMAIN . '/success.php?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => APP_DOMAIN . '/item.php?id=' . urlencode($item_id),
            'metadata[item_id]' => $item_id,
            'metadata[user_id]' => $user_id,
            'metadata[transaction_id]' => $transaction_id,
            'metadata[event_id]' => $event_id,
            'metadata[organization]' => $org_name,
            'metadata[event_name]' => $event_name
        ];

        // Create Stripe checkout session via API using event-specific keys
        $response = callStripeAPI('/v1/checkout/sessions', $session_data, 'POST', $secret_key);

        if (empty($response['id'])) {
            $error = $response['error']['message'] ?? 'Failed to create checkout session';
            return ['success' => false, 'error' => $error];
        }

        attachCheckoutSessionToPaymentRequest($transaction_id, $response['id']);

        return [
            'success' => true,
            'session_id' => $response['id'],
            'public_key' => $public_key,
            'event_id' => $event_id
        ];
    } catch (Exception $e) {
        error_log("Stripe session creation error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Payment processing error'];
    }
}

/**
 * Get or create a Stripe customer for a user
 * @param int $user_id User ID
 * @param string $email Email address (optional)
 * @param string $stripe_secret_key Stripe secret key (optional, uses global if not provided)
 * @return array|null Customer data with 'id' key
 */
function getOrCreateStripeCustomer($user_id, $email = '', $stripe_secret_key = '') {
    try {
        // Get user record
        $user = dbGetRow(
            "SELECT id, phone_number, full_name, stripe_customer_id FROM users WHERE id = ?",
            [(int)$user_id]
        );

        if (!$user) {
            return null;
        }

        // If customer already exists, return it
        if ($user['stripe_customer_id']) {
            $response = callStripeAPI('/v1/customers/' . $user['stripe_customer_id'], [], 'GET', $stripe_secret_key);
            if (!empty($response['id'])) {
                return $response;
            }
        }

        // Create new Stripe customer
        $customer_data = [
            'description' => 'User #' . $user_id . ' - ' . $user['full_name'],
            'phone' => $user['phone_number']
        ];

        if ($email) {
            $customer_data['email'] = $email;
        }

        $response = callStripeAPI('/v1/customers', $customer_data, 'POST', $stripe_secret_key);

        if (empty($response['id'])) {
            return null;
        }

        // Store customer ID
        dbUpdate(
            "UPDATE users SET stripe_customer_id = ? WHERE id = ?",
            [$response['id'], (int)$user_id]
        );

        return $response;
    } catch (Exception $e) {
        error_log("Stripe customer creation error: " . $e->getMessage());
        return null;
    }
}

/**
 * Handle Stripe webhook event
 * @param array $event Webhook event from Stripe
 * @param string $signature Stripe signature header
 * @return bool
 */
function handleStripeWebhook($event, $signature = '', $payload = '') {
    try {
        // Verify webhook signature if provided
        if ($signature && STRIPE_WEBHOOK_SECRET) {
            $payload_to_verify = $payload !== '' ? $payload : json_encode($event);
            if (!verifyStripeSignature($payload_to_verify, $signature)) {
                error_log("Stripe webhook signature verification failed");
                return false;
            }
        }

        $event_type = $event['type'] ?? '';

        switch ($event_type) {
            case 'checkout.session.completed':
                return processCheckoutCompleted($event['data']['object'] ?? []);
            case 'payment_intent.succeeded':
                return true; // Payment already handled via checkout.session.completed
            default:
                return true; // Ignore other event types
        }
    } catch (Exception $e) {
        error_log("Stripe webhook error: " . $e->getMessage());
        return false;
    }
}

/**
 * Process checkout.session.completed event
 * @param array $session Checkout session object
 * @return bool
 */
function processCheckoutCompleted($session) {
    if (empty($session['id'])) {
        return false;
    }

    try {
        $transaction = getPaymentRequestByCheckoutSession($session['id']);

        // Idempotency: Stripe retries/redelivers webhooks. If this transaction is
        // already marked paid, do nothing (prevents duplicate PAYMENT_COMPLETED
        // audit rows and redundant updates).
        if ($transaction && ($transaction['status'] ?? '') === 'paid') {
            return true;
        }

        // Get metadata
        $item_id = $session['metadata']['item_id'] ?? 0;
        $user_id = $session['metadata']['user_id'] ?? 0;
        $amount = isset($session['amount_total'])
            ? ((float)$session['amount_total'] / 100)
            : null;

        if (!$transaction && (!$item_id || !$user_id)) {
            error_log("Stripe webhook missing item_id or user_id");
            return false;
        }

        if (!$transaction) {
            if ($amount === null) {
                $item = dbGetRow(
                    "SELECT current_high_bid FROM items WHERE id = ?",
                    [(int)$item_id]
                );
                $amount = (float)($item['current_high_bid'] ?? 0);
            }

            $payment_request = ensurePendingPaymentRequest($item_id, $user_id, $amount);
            if (!$payment_request['success'] || $payment_request['already_paid']) {
                return $payment_request['already_paid'] ?? false;
            }

            $transaction = $payment_request['transaction'];
            attachCheckoutSessionToPaymentRequest((int)$transaction['id'], $session['id']);
        }

        $final_amount = $amount !== null
            ? $amount
            : (float)$transaction['amount'];

        dbUpdate(
            "UPDATE transactions
             SET status = ?, stripe_payment_intent_id = ?, amount = ?
             WHERE id = ?",
            ['paid', $session['payment_intent'] ?? '', $final_amount, (int)$transaction['id']]
        );

        // Log completion
        dbInsert(
            "INSERT INTO audit_log (event_type, user_id, item_id, description, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [
                'PAYMENT_COMPLETED',
                (int)($user_id ?: $transaction['user_id']),
                (int)($item_id ?: $transaction['item_id']),
                'Payment completed via Stripe'
            ]
        );

        return true;
    } catch (Exception $e) {
        error_log("Stripe payment processing error: " . $e->getMessage());
        return false;
    }
}

/**
 * Verify Stripe webhook signature against the endpoint's signing secret.
 *
 * SECURITY: verification is done ONLY against the dedicated webhook signing
 * secret (a `whsec_...` value in STRIPE_WEBHOOK_SECRET). The previous version
 * tried an event's stored Stripe API *secret key* (`sk_...`) as an HMAC secret,
 * choosing which one by the `event_id` in the UNVERIFIED payload — so any event
 * organizer who knew their own API key could forge a `checkout.session.completed`
 * for any item and mark it paid without paying. An API key is not a webhook
 * secret; that whole path is removed. Per-event webhook endpoints, if ever
 * added, must store their own `whsec_` secret in a dedicated column.
 *
 * @param string $payload Raw request body
 * @param string $signature Signature header
 * @param int $event_id Unused; kept for call-site compatibility
 * @return bool
 */
function verifyStripeSignature($payload, $signature, $event_id = 0) {
    if (!STRIPE_WEBHOOK_SECRET) {
        error_log('[STRIPE] Webhook rejected: STRIPE_WEBHOOK_SECRET is not configured (fail-closed).');
        return false;
    }

    return verifyStripeSignatureHeader($payload, $signature, STRIPE_WEBHOOK_SECRET);
}

/**
 * Verify a Stripe-Signature header with the raw payload and endpoint secret.
 * @param string $payload Raw request body
 * @param string $signature Signature header
 * @param string $secret Webhook signing secret
 * @param int $tolerance_seconds Maximum timestamp age
 * @return bool
 */
function verifyStripeSignatureHeader($payload, $signature, $secret, $tolerance_seconds = 300) {
    if (!$payload || !$signature || !$secret) {
        return false;
    }

    $timestamp = null;
    $signatures = [];

    foreach (explode(',', $signature) as $part) {
        $pieces = explode('=', trim($part), 2);
        if (count($pieces) !== 2) {
            continue;
        }

        [$key, $value] = $pieces;
        if ($key === 't') {
            $timestamp = (int)$value;
        } elseif ($key === 'v1') {
            $signatures[] = $value;
        }
    }

    if (!$timestamp || empty($signatures)) {
        return false;
    }

    if ($tolerance_seconds > 0 && abs(time() - $timestamp) > $tolerance_seconds) {
        return false;
    }

    $signed_payload = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signed_payload, $secret);

    foreach ($signatures as $candidate) {
        if (hash_equals($expected, $candidate)) {
            return true;
        }
    }

    return false;
}

/**
 * Call Stripe API endpoint
 * @param string $endpoint API endpoint path (e.g. '/v1/checkout/sessions')
 * @param array $data Request data
 * @param string $method HTTP method
 * @param string $stripe_secret_key Stripe secret key (optional, uses global if not provided)
 * @return array API response
 */
function callStripeAPI($endpoint, $data = [], $method = 'POST', $stripe_secret_key = '') {
    $secret_key = $stripe_secret_key ?: STRIPE_SECRET_KEY;

    if (!$secret_key) {
        throw new Exception('Stripe API key not configured');
    }

    $url = 'https://api.stripe.com' . $endpoint;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $secret_key . ':');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($method === 'POST' && !empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);

    if ($http_code >= 400) {
        error_log("Stripe API error ($http_code): " . $response);
    }

    return $decoded ?? [];
}

?>
