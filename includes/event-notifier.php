<?php
// ============================================================
// EVENT NOTIFIER — Push Notification Dispatcher
// Sends push notifications when auction events occur
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';
require_once __DIR__ . '/notifications.php';

/**
 * Track notification delivery status with retry capability
 * @param int $user_id User ID who received the notification
 * @param int $item_id Item ID for the notification
 * @param string $type Notification type (outbid, won, ending_soon, etc)
 * @param string $delivery_channel Channel used (push, sms, both)
 * @param string $status delivery_status (pending, sent, failed)
 * @param string|null $error_message Optional error details
 * @return int|false Notification ID for follow-up retry
 */
function logNotificationDelivery($user_id, $item_id, $type, $delivery_channel, $status, $error_message = null) {
    // Use the shared singleton connection (there is no global $mysqli).
    $mysqli = getDB();

    $metadata = json_encode([
        'channel' => $delivery_channel,
        'status' => $status,
        'error' => $error_message,
        'attempt_time' => date('Y-m-d H:i:s')
    ]);

    // Insert main notification record
    $notification_id = dbInsert(
        "INSERT INTO notifications (user_id, item_id, type, title, message, sent_via, data, delivery_status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
        [
            (int)$user_id,
            (int)$item_id,
            $type,
            ucfirst($type) . ' notification for item #' . $item_id,
            $error_message ?: 'Notification delivered via ' . $delivery_channel,
            $delivery_channel,
            $metadata,
            $status
        ]
    );

    if (!$notification_id) {
        error_log("[NOTIFY] Failed to log notification delivery: " . $mysqli->error);
        return false;
    }

    // If failed, create a retry entry
    if ($status === 'failed') {
        $next_retry = date('Y-m-d H:i:s', time() + 300); // Retry in 5 minutes
        $retry_id = dbInsert(
            "INSERT INTO notifications_log (notification_id, user_id, item_id, notification_type, delivery_channel, delivery_status, attempt_number, max_attempts, error_message, created_at, next_retry_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)",
            [
                (int)$notification_id,
                (int)$user_id,
                (int)$item_id,
                $type,
                $delivery_channel,
                'failed',
                1,
                3,
                $error_message ?: 'Initial delivery attempt failed',
                $next_retry
            ]
        );

        if ($retry_id) {
            error_log("[NOTIFY] Failed delivery logged for retry. Notification ID: $notification_id, Retry ID: $retry_id");
        }
    } else if ($status === 'sent') {
        // Log successful delivery
        dbInsert(
            "INSERT INTO notifications_log (notification_id, user_id, item_id, notification_type, delivery_channel, delivery_status, attempt_number, max_attempts, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                (int)$notification_id,
                (int)$user_id,
                (int)$item_id,
                $type,
                $delivery_channel,
                'sent',
                1,
                1
            ]
        );
    }

    return $notification_id;
}

/**
 * Retry failed notification deliveries
 * Checks notifications_log for failed deliveries and attempts to resend
 * @return array Summary of retry results
 */
function retryFailedNotifications() {
    $results = [
        'total_retried' => 0,
        'successful' => 0,
        'failed' => 0
    ];

    // Get failed notifications that are ready for retry
    $pending_retries = dbGetAll(
        "SELECT nl.id, nl.notification_id, nl.user_id, nl.item_id, nl.notification_type, nl.delivery_channel, nl.attempt_number, nl.max_attempts, n.title, n.message
         FROM notifications_log nl
         JOIN notifications n ON n.id = nl.notification_id
         WHERE nl.delivery_status = 'failed'
         AND nl.attempt_number < nl.max_attempts
         AND nl.next_retry_at <= NOW()
         ORDER BY nl.created_at ASC
         LIMIT 10",
        []
    );

    foreach ($pending_retries as $retry) {
        $results['total_retried']++;
        $attempt_num = (int)$retry['attempt_number'] + 1;

        error_log("[NOTIFY] Retrying notification. Notification ID: {$retry['notification_id']}, Attempt: {$attempt_num}");

        // Attempt to resend based on channel
        $resend_success = false;

        if ($retry['delivery_channel'] === 'sms' || $retry['delivery_channel'] === 'both') {
            $user = dbGetRow("SELECT phone_number FROM users WHERE id = ?", [(int)$retry['user_id']]);
            if ($user && !empty($user['phone_number'])) {
                $sms_result = sendTwilioSMS($user['phone_number'], $retry['message'], (int)$retry['user_id']);
                if ($sms_result && $sms_result['success']) {
                    $resend_success = true;
                }
            }
        }

        if ($retry['delivery_channel'] === 'push' || $retry['delivery_channel'] === 'both') {
            $push_results = sendPushNotifications((int)$retry['user_id'], [
                'title' => $retry['title'],
                'body' => $retry['message'],
                'icon' => '/images/sbb-icon-192.png',
                'badge' => '/images/sbb-badge-72.png'
            ]);

            if (!empty($push_results)) {
                foreach ($push_results as $result) {
                    if ($result['status'] === 'success') {
                        $resend_success = true;
                        break;
                    }
                }
            }
        }

        // Update retry log
        $new_status = $resend_success ? 'sent' : 'failed';
        $next_retry = $resend_success ? null : date('Y-m-d H:i:s', time() + 600); // 10 minutes if still failing

        dbUpdate(
            "UPDATE notifications_log SET delivery_status = ?, attempt_number = ?, next_retry_at = ?, updated_at = NOW() WHERE id = ?",
            [$new_status, $attempt_num, $next_retry, (int)$retry['id']]
        );

        // Update main notification record
        if ($resend_success) {
            dbUpdate(
                "UPDATE notifications SET delivery_status = 'sent' WHERE id = ?",
                [(int)$retry['notification_id']]
            );
            $results['successful']++;
        } else {
            $results['failed']++;
        }
    }

    return $results;
}

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
                $code = sendPushMessageToEndpoint(
                    $sub['endpoint'],
                    $sub['auth_key'],
                    $sub['p256dh_key'],
                    $payload
                );

                if (in_array($code, [200, 201], true)) {
                    // Update last sent timestamp
                    dbUpdate(
                        "UPDATE push_subscriptions SET last_sent_at = NOW() WHERE id = ?",
                        [(int)$sub['id']]
                    );

                    $results[] = ['user_id' => $user_id, 'status' => 'success', 'endpoint' => $sub['endpoint']];
                } elseif (in_array($code, [404, 410], true)) {
                    // Only 404/410 mean the subscription is permanently gone —
                    // deactivate. A transient 5xx/429/timeout (or a systemic VAPID
                    // failure) must NOT nuke every subscriber, or the retry path can
                    // never succeed because everyone is now inactive.
                    dbUpdate(
                        "UPDATE push_subscriptions SET is_active = 0 WHERE id = ?",
                        [(int)$sub['id']]
                    );
                    $results[] = ['user_id' => $user_id, 'status' => 'gone', 'endpoint' => $sub['endpoint']];
                } else {
                    // Transient — keep active for a later retry.
                    $results[] = ['user_id' => $user_id, 'status' => 'failed', 'endpoint' => $sub['endpoint']];
                }
            } catch (\Throwable $e) {
                // \Throwable (not just Exception) so a TypeError/Error from crypto
                // can never bubble up and fatal the caller (e.g. the cron closer).
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
    // Encrypt the payload (RFC 8291 aes128gcm). A failure here means we can't
    // produce a decryptable message, so report failure honestly rather than
    // POSTing garbage the browser will silently drop.
    try {
        $body = encryptPayload(
            json_encode($payload),
            base64_decode(strtr($p256dh, '-_', '+/')),
            base64_decode(strtr($auth, '-_', '+/'))
        );
    } catch (\Throwable $e) {
        error_log('[PUSH] Encryption failed: ' . $e->getMessage());
        return 0;
    }

    // Create Authorization header. VAPID JWT signing can throw a TypeError (a
    // PHP Error, not an Exception) on a malformed key; contain it here so it can
    // never escape and fatal a caller loop (e.g. the auction closer).
    try {
        $vapidHeader = createVAPIDHeader($endpoint);
    } catch (\Throwable $e) {
        error_log('[PUSH] VAPID header generation failed: ' . $e->getMessage());
        return 0;
    }

    // Send to push service (aes128gcm: single Content-Encoding header, body carries salt+key).
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'TTL: 2419200',
        'Content-Length: ' . strlen($body),
        'Authorization: ' . $vapidHeader
    ]);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Push service accepts 201 Created or 200 OK
    $success = in_array($httpCode, [200, 201]);

    if (!$success) {
        error_log("[PUSH] Push delivery failed ($httpCode): " . substr((string)$response, 0, 200));
    }

    // Return the HTTP status so the caller can distinguish a permanently-gone
    // subscription (404/410 → deactivate) from a transient failure (5xx/429/0 →
    // keep it and retry later). 0 means we never got to send.
    return (int)$httpCode;
}

/**
 * Encryption/pre-send failure sentinel handled by returning 0 above; see callers.
 */

/**
 * Wrap a raw uncompressed P-256 public point (65 bytes: 0x04||X||Y) as a PEM
 * SubjectPublicKeyInfo so openssl can import it. Uses the fixed ASN.1 prefix for
 * id-ecPublicKey / prime256v1.
 */
function p256RawPointToPem($raw65) {
    $prefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
    $der = $prefix . $raw65;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/**
 * Encrypt a Web Push payload per RFC 8291 (aes128gcm content encoding), using a
 * real ECDH shared secret. Returns the binary message body ready to POST with a
 * "Content-Encoding: aes128gcm" header, or throws on failure.
 *
 * NOTE: this replaces a prior stub that never performed ECDH (it HKDF'd only the
 * auth secret), so browsers could never decrypt the payload even though the push
 * service returned 200/201. This implementation follows RFC 8291 + RFC 8188.
 *
 * @param string $message      Plaintext (JSON) payload
 * @param string $userPublicKey Raw 65-byte subscriber P-256 public key (decoded)
 * @param string $userAuth     Raw 16-byte subscriber auth secret (decoded)
 * @return string Binary aes128gcm message body
 */
function encryptPayload($message, $userPublicKey, $userAuth) {
    if (strlen($userPublicKey) !== 65 || $userPublicKey[0] !== "\x04") {
        throw new RuntimeException('Invalid subscriber p256dh key');
    }

    // Ephemeral (application server) EC key pair.
    $ephemeral = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1'
    ]);
    if ($ephemeral === false) {
        throw new RuntimeException('Failed to create ephemeral EC key');
    }
    $details = openssl_pkey_get_details($ephemeral);
    $asX = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
    $asY = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    $asPublic = "\x04" . $asX . $asY; // 65-byte uncompressed point

    // Import subscriber public key and derive the ECDH shared secret.
    $peer = openssl_pkey_get_public(p256RawPointToPem($userPublicKey));
    if ($peer === false) {
        throw new RuntimeException('Failed to import subscriber public key');
    }
    $sharedSecret = openssl_pkey_derive($peer, $ephemeral, 32);
    if ($sharedSecret === false) {
        throw new RuntimeException('ECDH derivation failed');
    }

    // RFC 8291: combine the ECDH secret with the auth secret to get the IKM.
    $keyInfo = "WebPush: info\x00" . $userPublicKey . $asPublic;
    $ikm = hash_hkdf('sha256', $sharedSecret, 32, $keyInfo, $userAuth);

    // RFC 8188 aes128gcm: derive content-encryption key and nonce from a salt.
    $salt = random_bytes(16);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

    // Single record: plaintext followed by the 0x02 last-record delimiter.
    $tag = '';
    $ciphertext = openssl_encrypt(
        $message . "\x02",
        'aes-128-gcm',
        $cek,
        OPENSSL_RAW_DATA,
        $nonce,
        $tag
    );
    if ($ciphertext === false) {
        throw new RuntimeException('AES-128-GCM encryption failed');
    }

    // Header: salt(16) || record_size(uint32=4096) || idlen(1)=65 || as_public(65)
    $header = $salt . pack('N', 4096) . chr(strlen($asPublic)) . $asPublic;

    return $header . $ciphertext . $tag;
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
        'sub' => 'mailto:noreply@silentbidpro.com'
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
 * Get event ID from item ID
 * @param int $item_id Item ID
 * @return int|null Event ID or null if not found
 */
function getEventIdFromItem($item_id) {
    $item = dbGetRow(
        "SELECT event_id FROM items WHERE id = ?",
        [(int)$item_id]
    );
    return $item ? $item['event_id'] : null;
}

/**
 * Get event settings with defaults
 * @param int $event_id Event ID
 * @return array Settings with defaults
 */
function getEventSettings($event_id) {
    $settings = dbGetRow(
        "SELECT * FROM event_settings WHERE event_id = ?",
        [(int)$event_id]
    );

    // Return with defaults if not found
    return $settings ?: [
        'sms_enabled' => 1,
        'outbid_sms_template' => null,
        'winner_sms_template' => null
    ];
}

/**
 * Check if SMS is enabled for an event
 * @param int $event_id Event ID
 * @return bool SMS enabled
 */
function shouldSendSMS($event_id) {
    $settings = getEventSettings($event_id);
    // Default to enabled if no settings yet
    return (bool)($settings['sms_enabled'] ?? true);
}

/**
 * Get SMS message using custom template or default
 * @param string $template_text Custom template (may be null)
 * @param string $default Default message
 * @param array $variables Template variables {TITLE, AMOUNT, URL, etc}
 * @return string Final message
 */
function formatSMSMessage($template_text, $default, $variables = []) {
    if (empty($template_text)) {
        return $default;
    }

    // Replace template variables
    $message = $template_text;
    foreach ($variables as $key => $value) {
        $message = str_replace('{' . $key . '}', $value, $message);
    }

    return $message;
}

/**
 * Notify when bid is placed with delivery tracking
 * @param int $item_id Item ID
 * @param int $new_bidder_id User ID of new bidder
 * @param int|null $previous_bidder_id User ID of previous high bidder
 * @param string $item_title Item title
 * @param float $new_bid_amount New bid amount
 * @return array Delivery results with status
 */
function notifyBidPlaced($item_id, $new_bidder_id, $previous_bidder_id, $item_title, $new_bid_amount) {
    $delivery_results = [
        'push_sent' => false,
        'sms_sent' => false,
        'errors' => []
    ];

    // Get event context
    $event_id = getEventIdFromItem($item_id);
    $should_send_sms = $event_id ? shouldSendSMS($event_id) : true;

    // Notify outbid user
    if ($previous_bidder_id) {
        $previous_bidder = dbGetRow(
            "SELECT id, phone_number FROM users WHERE id = ?",
            [(int)$previous_bidder_id]
        );

        if ($previous_bidder) {
            // Send push notification
            $push_results = sendPushNotifications($previous_bidder_id, [
                'title' => 'You\'ve been outbid!',
                'body' => "Someone bid " . formatCurrency($new_bid_amount) . " on '{$item_title}'",
                'icon' => '/images/sbb-icon-192.png',
                'badge' => '/images/sbb-badge-72.png',
                'data' => ['item_id' => $item_id, 'action' => 'view_item']
            ]);

            if (!empty($push_results)) {
                $push_sent = false;
                foreach ($push_results as $result) {
                    if ($result['status'] === 'success') {
                        $push_sent = true;
                        break;
                    }
                }
                $delivery_results['push_sent'] = $push_sent;

                // Log push delivery
                logNotificationDelivery(
                    (int)$previous_bidder['id'],
                    $item_id,
                    'outbid',
                    'push',
                    $push_sent ? 'sent' : 'failed',
                    $push_sent ? null : 'Push delivery failed to all endpoints'
                );
            }

            // Send SMS if enabled for this event
            if ($should_send_sms && !empty($previous_bidder['phone_number'])) {
                $settings = $event_id ? getEventSettings($event_id) : null;

                // Build from the server-configured public URL, never the
                // attacker-controllable Host header (SMS phishing vector), and
                // never the not-yet-live .com fallback (dead link).
                $item_url = rtrim(PUBLIC_SITE_URL, '/') . "/item.php?id={$item_id}";
                $default_message = "You've been outbid on '{$item_title}'! Bid again: {$item_url}";

                $sms_result = null;
                if ($settings && !empty($settings['outbid_sms_template'])) {
                    $message = formatSMSMessage($settings['outbid_sms_template'], $default_message, [
                        'TITLE' => $item_title,
                        'AMOUNT' => formatCurrency($new_bid_amount),
                        'URL' => $item_url
                    ]);
                    $sms_result = sendTwilioSMS($previous_bidder['phone_number'], $message, (int)$previous_bidder['id']);
                } else {
                    $sms_result = sendOutbidAlert($previous_bidder['phone_number'], $item_title, $item_id, (int)$previous_bidder['id']);
                }

                if ($sms_result && is_array($sms_result)) {
                    $delivery_results['sms_sent'] = $sms_result['success'];
                    if (!$sms_result['success']) {
                        $delivery_results['errors'][] = 'SMS: ' . ($sms_result['error'] ?? 'Unknown error');
                    }

                    // Log SMS delivery
                    logNotificationDelivery(
                        (int)$previous_bidder['id'],
                        $item_id,
                        'outbid',
                        'sms',
                        $sms_result['success'] ? 'sent' : 'failed',
                        $sms_result['error'] ?? null
                    );
                }
            }
        }
    }

    // Notify other watchers (optional: brief in-app only)
    // This is handled client-side via polling refresh

    // Log to audit trail
    dbInsert(
        "INSERT INTO audit_log (event_type, user_id, item_id, description, metadata, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())",
        [
            'BID_NOTIFICATION_SENT',
            $new_bidder_id,
            $item_id,
            'Outbid notification delivery attempt',
            json_encode($delivery_results)
        ]
    );

    return $delivery_results;
}

/**
 * Notify winner of auction closure with delivery tracking
 * @param int $item_id Item ID
 * @param int $winner_id User ID of winner
 * @param string $item_title Item title
 * @param float $winning_amount Final winning bid
 * @param string $checkout_url Optional checkout URL for SMS
 * @return array Delivery results with status
 */
function notifyWinner($item_id, $winner_id, $item_title, $winning_amount, $checkout_url = '') {
    $delivery_results = [
        'push_sent' => false,
        'sms_sent' => false,
        'errors' => []
    ];

    $winner = dbGetRow(
        "SELECT id, phone_number FROM users WHERE id = ?",
        [(int)$winner_id]
    );

    if (!$winner) {
        return $delivery_results;
    }

    // Send push notification
    $push_results = sendPushNotifications($winner_id, [
        'title' => 'You won!',
        'body' => "Congratulations! You won '{$item_title}' for " . formatCurrency($winning_amount),
        'icon' => '/images/sbb-icon-192.png',
        'badge' => '/images/sbb-badge-72.png',
        'data' => ['item_id' => $item_id, 'action' => 'checkout']
    ]);

    if (!empty($push_results)) {
        $push_sent = false;
        foreach ($push_results as $result) {
            if ($result['status'] === 'success') {
                $push_sent = true;
                break;
            }
        }
        $delivery_results['push_sent'] = $push_sent;

        // Log push delivery
        logNotificationDelivery(
            (int)$winner['id'],
            $item_id,
            'won',
            'push',
            $push_sent ? 'sent' : 'failed',
            $push_sent ? null : 'Push delivery failed to all endpoints'
        );
    }

    // Send SMS if enabled for this event
    $event_id = getEventIdFromItem($item_id);
    $should_send_sms = $event_id ? shouldSendSMS($event_id) : true;

    if ($should_send_sms && !empty($winner['phone_number'])) {
        $settings = $event_id ? getEventSettings($event_id) : null;

        if (!$checkout_url) {
            $checkout_url = rtrim(PUBLIC_SITE_URL, '/') . "/checkout.php?item_id={$item_id}";
        }

        $sms_result = null;
        if ($settings && !empty($settings['winner_sms_template'])) {
            $message = formatSMSMessage($settings['winner_sms_template'], '', [
                'TITLE' => $item_title,
                'AMOUNT' => formatCurrency($winning_amount),
                'URL' => $checkout_url
            ]);
            $sms_result = sendTwilioSMS($winner['phone_number'], $message, (int)$winner['id']);
        } else {
            $sms_result = sendWinnerNotification($winner['phone_number'], $item_title, $winning_amount, $checkout_url, (int)$winner['id']);
        }

        if ($sms_result && is_array($sms_result)) {
            $delivery_results['sms_sent'] = $sms_result['success'];
            if (!$sms_result['success']) {
                $delivery_results['errors'][] = 'SMS: ' . ($sms_result['error'] ?? 'Unknown error');
            }

            // Log SMS delivery
            logNotificationDelivery(
                (int)$winner['id'],
                $item_id,
                'won',
                'sms',
                $sms_result['success'] ? 'sent' : 'failed',
                $sms_result['error'] ?? null
            );
        }
    }

    // Log to audit trail
    dbInsert(
        "INSERT INTO audit_log (event_type, user_id, item_id, description, metadata, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())",
        [
            'WINNER_NOTIFICATION_SENT',
            $winner_id,
            $item_id,
            'Winner notification delivery attempt',
            json_encode($delivery_results)
        ]
    );

    return $delivery_results;
}

/**
 * Format amount as currency (helper)
 */
function formatCurrency($amount) {
    return '$' . number_format((float)$amount, 2);
}
