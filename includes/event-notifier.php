<?php
// ============================================================
// EVENT NOTIFIER — Push Notification Dispatcher
// Sends push notifications when auction events occur
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';
require_once __DIR__ . '/notifications.php';

/**
 * Send push notification to user's subscribed devices
 * @param int|array $user_ids User ID(s) to notify
 * @param array $payload {title, body, icon, badge, data}
 * @return array Results of sending to each subscription
 */
function sendPushNotifications($user_ids, $payload) {
    if (empty(VAPID_PRIVATE_KEY)) {
        error_log('[PUSH] VAPID private key not configured');
        return [];
    }

    $user_ids = is_array($user_ids) ? $user_ids : [$user_ids];
    $results = [];

    foreach ($user_ids as $user_id) {
        // Get all active subscriptions for user
        $subscriptions = dbGetAll(
            "SELECT id, endpoint, auth_key, p256dh_key FROM push_subscriptions
             WHERE user_id = ? AND is_active = 1",
            [(int)$user_id]
        );

        foreach ($subscriptions as $sub) {
            try {
                $sent = sendPushMessageToEndpoint(
                    $sub['endpoint'],
                    $sub['auth_key'],
                    $sub['p256dh_key'],
                    $payload
                );

                if ($sent) {
                    // Update last sent timestamp
                    dbUpdate(
                        "UPDATE push_subscriptions SET last_sent_at = NOW() WHERE id = ?",
                        [(int)$sub['id']]
                    );

                    $results[] = ['user_id' => $user_id, 'status' => 'success', 'endpoint' => $sub['endpoint']];
                } else {
                    // Mark as inactive if delivery failed
                    dbUpdate(
                        "UPDATE push_subscriptions SET is_active = 0 WHERE id = ?",
                        [(int)$sub['id']]
                    );

                    $results[] = ['user_id' => $user_id, 'status' => 'failed', 'endpoint' => $sub['endpoint']];
                }
            } catch (Exception $e) {
                error_log("[PUSH] Error sending to {$sub['endpoint']}: " . $e->getMessage());
                $results[] = ['user_id' => $user_id, 'status' => 'error', 'endpoint' => $sub['endpoint']];
            }
        }
    }

    return $results;
}

/**
 * Send encrypted push message to a single endpoint
 * @param string $endpoint Push service endpoint
 * @param string $auth Auth key (base64)
 * @param string $p256dh P256DH key (base64)
 * @param array $payload Message payload
 * @return bool Success
 */
function sendPushMessageToEndpoint($endpoint, $auth, $p256dh, $payload) {
    // Encrypt the payload
    $encryptedPayload = encryptPayload(
        json_encode($payload),
        base64_decode($p256dh),
        base64_decode($auth)
    );

    // Create Authorization header
    $vapidHeader = createVAPIDHeader($endpoint);

    // Send to push service
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/octet-stream',
        'Content-Length: ' . strlen($encryptedPayload['ciphertext']),
        'Authorization: ' . $vapidHeader,
        'Crypto-Key: dh=' . $encryptedPayload['dh'],
        'Encryption: salt=' . $encryptedPayload['salt']
    ]);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $encryptedPayload['ciphertext']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Push service accepts 201 Created or 200 OK
    $success = in_array($httpCode, [200, 201]);

    if (!$success) {
        error_log("[PUSH] Push delivery failed ($httpCode): " . substr($response, 0, 200));
    }

    return $success;
}

/**
 * Encrypt payload using ECDH and AES-GCM
 * @param string $message Message to encrypt
 * @param string $userPublicKey User's P256 public key
 * @param string $userAuth User's auth secret
 * @return array {ciphertext, dh, salt}
 */
function encryptPayload($message, $userPublicKey, $userAuth) {
    // Generate ephemeral key pair
    $ephemeral = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1'
    ]);

    // Extract public key
    $details = openssl_pkey_get_details($ephemeral);
    $ephemeralPublicKey = $details['key'];

    // ECDH shared secret (simplified - real implementation needs full ECDH)
    // For production, use a proper Web Push library
    $salt = openssl_random_pseudo_bytes(16);
    $nonce = openssl_random_pseudo_bytes(12);

    // AES-128-GCM encryption
    $encryptedMessage = openssl_encrypt(
        $message,
        'aes-128-gcm',
        substr(hash_hkdf('sha256', $userAuth . chr(0), $salt, 'Content-Encoding: aes128gcm'), 0, 16),
        OPENSSL_RAW_DATA,
        $nonce,
        $tag
    );

    return [
        'ciphertext' => $nonce . $encryptedMessage . $tag,
        'dh' => base64_encode(substr($ephemeralPublicKey, -65)), // P256 point format
        'salt' => base64_encode($salt)
    ];
}

/**
 * Create VAPID Authorization header
 * @param string $endpoint Push service endpoint
 * @return string Authorization header value
 */
function createVAPIDHeader($endpoint) {
    $header = [
        'typ' => 'JWT',
        'alg' => 'ES256'
    ];

    $claims = [
        'aud' => parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST),
        'exp' => time() + 3600,
        'sub' => 'mailto:noreply@silentbidbuddy.com'
    ];

    // Create JWT
    $headerEncoded = base64UrlEncode(json_encode($header));
    $claimsEncoded = base64UrlEncode(json_encode($claims));
    $signatureInput = "{$headerEncoded}.{$claimsEncoded}";

    // Sign with private key
    $privateKey = openssl_pkey_get_private('-----BEGIN EC PRIVATE KEY-----
' . wordwrap(VAPID_PRIVATE_KEY, 64, "\n", true) . '
-----END EC PRIVATE KEY-----');

    openssl_sign($signatureInput, $signature, $privateKey, 'sha256');
    $signatureEncoded = base64UrlEncode($signature);

    $jwt = "{$signatureInput}.{$signatureEncoded}";

    return "vapid t={$jwt}, k=" . VAPID_PUBLIC_KEY;
}

/**
 * Base64 URL-safe encode
 */
function base64UrlEncode($string) {
    return rtrim(strtr(base64_encode($string), '+/', '-_'), '=');
}

/**
 * Notify when bid is placed
 * @param int $item_id Item ID
 * @param int $new_bidder_id User ID of new bidder
 * @param int|null $previous_bidder_id User ID of previous high bidder
 * @param string $item_title Item title
 * @param float $new_bid_amount New bid amount
 */
function notifyBidPlaced($item_id, $new_bidder_id, $previous_bidder_id, $item_title, $new_bid_amount) {
    // Notify outbid user
    if ($previous_bidder_id) {
        $previous_bidder = dbGetRow(
            "SELECT phone_number FROM users WHERE id = ?",
            [(int)$previous_bidder_id]
        );

        if ($previous_bidder) {
            // Send push notification
            sendPushNotifications($previous_bidder_id, [
                'title' => 'You\'ve been outbid!',
                'body' => "Someone bid " . formatCurrency($new_bid_amount) . " on '{$item_title}'",
                'icon' => '/images/sbb-icon-192.png',
                'badge' => '/images/sbb-badge-72.png',
                'data' => ['item_id' => $item_id, 'action' => 'view_item']
            ]);

            // Keep existing SMS alert
            sendOutbidAlert($previous_bidder['phone_number'], $item_title, $item_id);
        }
    }

    // Notify other watchers (optional: brief in-app only)
    // This is handled client-side via polling refresh

    // Log to audit trail
    dbInsert(
        "INSERT INTO audit_log (event_type, user_id, item_id, description, created_at)
         VALUES (?, ?, ?, ?, NOW())",
        ['BID_NOTIFICATION_SENT', $new_bidder_id, $item_id, 'Outbid push notification sent']
    );
}

/**
 * Notify winner of auction closure
 * @param int $item_id Item ID
 * @param int $winner_id User ID of winner
 * @param string $item_title Item title
 * @param float $winning_amount Final winning bid
 */
function notifyWinner($item_id, $winner_id, $item_title, $winning_amount) {
    $winner = dbGetRow(
        "SELECT phone_number FROM users WHERE id = ?",
        [(int)$winner_id]
    );

    if (!$winner) {
        return;
    }

    // Send push notification
    sendPushNotifications($winner_id, [
        'title' => 'You won!',
        'body' => "Congratulations! You won '{$item_title}' for " . formatCurrency($winning_amount),
        'icon' => '/images/sbb-icon-192.png',
        'badge' => '/images/sbb-badge-72.png',
        'data' => ['item_id' => $item_id, 'action' => 'checkout']
    ]);

    // Keep existing SMS (will be sent by auction-engine.php separately)

    // Log to audit trail
    dbInsert(
        "INSERT INTO audit_log (event_type, user_id, item_id, description, created_at)
         VALUES (?, ?, ?, ?, NOW())",
        ['WINNER_NOTIFICATION_SENT', $winner_id, $item_id, 'Winner push notification sent']
    );
}

/**
 * Format amount as currency (helper)
 */
function formatCurrency($amount) {
    return '$' . number_format((float)$amount, 2);
}
