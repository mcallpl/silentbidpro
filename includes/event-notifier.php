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
    global $mysqli;

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
    global $mysqli;

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

                $item_url = "https://" . ($_SERVER['HTTP_HOST'] ?? 'silentbidbuddy.com') . "/item.php?id={$item_id}";
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
            $checkout_url = "https://" . ($_SERVER['HTTP_HOST'] ?? 'silentbidbuddy.com') . "/checkout.php?item_id={$item_id}";
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
