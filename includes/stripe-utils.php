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
        $payment_request = ensurePendingPaymentRequest($item_id, $user_id, $amount);
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
        $customer = getOrCreateStripeCustomer($user_id, $user_email);
        if (!$customer || empty($customer['id'])) {
            return ['success' => false, 'error' => 'Failed to create payment customer'];
        }

        // Prepare checkout session data
        $session_data = [
            'payment_method_types' => 'card',
            'customer' => $customer['id'],
            'line_items[0][price_data][currency]' => 'usd',
            'line_items[0][price_data][product_data][name]' => $item_title,
            'line_items[0][price_data][product_data][description]' => 'Silent Auction Item #' . $item_id,
            'line_items[0][price_data][unit_amount]' => (int)($amount * 100),
            'line_items[0][quantity]' => '1',
            'mode' => 'payment',
            'success_url' => APP_DOMAIN . '/success.php?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => APP_DOMAIN . '/item.php?id=' . urlencode($item_id),
            'metadata[item_id]' => $item_id,
            'metadata[user_id]' => $user_id,
            'metadata[transaction_id]' => $transaction_id
        ];

        // Create Stripe checkout session via API
        $response = callStripeAPI('/v1/checkout/sessions', $session_data, 'POST');

        if (empty($response['id'])) {
            $error = $response['error']['message'] ?? 'Failed to create checkout session';
            return ['success' => false, 'error' => $error];
        }

        attachCheckoutSessionToPaymentRequest($transaction_id, $response['id']);

        return [
            'success' => true,
            'session_id' => $response['id'],
            'public_key' => STRIPE_PUBLISHABLE_KEY
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
 * @return array|null Customer data with 'id' key
 */
function getOrCreateStripeCustomer($user_id, $email = '') {
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
            $response = callStripeAPI('/v1/customers/' . $user['stripe_customer_id'], [], 'GET');
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

        $response = callStripeAPI('/v1/customers', $customer_data, 'POST');

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
 * Verify Stripe webhook signature.
 * @param string $payload Raw request body
 * @param string $signature Signature header
 * @return bool
 */
function verifyStripeSignature($payload, $signature) {
    if (!STRIPE_WEBHOOK_SECRET) {
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
 * @return array API response
 */
function callStripeAPI($endpoint, $data = [], $method = 'POST') {
    if (!STRIPE_SECRET_KEY) {
        throw new Exception('Stripe API key not configured');
    }

    $url = 'https://api.stripe.com' . $endpoint;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ':');
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
