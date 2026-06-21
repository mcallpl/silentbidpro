<?php
// ============================================================
// NOTIFICATIONS MODULE
// Twilio SMS integration for alerts and notifications
// ============================================================

require_once __DIR__ . '/../config.php';

/**
 * Validate phone number format
 * @param string $phone Phone number to validate
 * @return bool Valid format
 */
function isValidPhoneNumber($phone) {
    if (empty($phone)) {
        return false;
    }

    // Basic validation: should start with + and contain only digits
    return preg_match('/^\+\d{10,15}$/', $phone) === 1;
}

/**
 * Send SMS via Twilio REST API with error tracking
 * @param string $to Recipient phone number (+1XXXXXXXXXX format)
 * @param string $message Message body (max 160 chars for best delivery)
 * @param int|null $user_id Optional user ID for logging
 * @return array ['success' => bool, 'message_sid' => string|null, 'error' => string|null]
 */
function sendTwilioSMS($to, $message, $user_id = null) {
    // Validate phone number
    if (!isValidPhoneNumber($to)) {
        error_log("[SMS] Invalid phone number format: " . substr($to, 0, 5) . "***");
        return [
            'success' => false,
            'message_sid' => null,
            'error' => 'Invalid phone number format'
        ];
    }

    if (empty(TWILIO_ACCOUNT_SID) || empty(TWILIO_AUTH_TOKEN)) {
        error_log("[SMS] Twilio credentials not configured");
        return [
            'success' => false,
            'message_sid' => null,
            'error' => 'SMS service not configured'
        ];
    }

    // Ensure message is not too long
    if (strlen($message) > 1600) {
        error_log("[SMS] Message too long: " . strlen($message) . " chars");
        return [
            'success' => false,
            'message_sid' => null,
            'error' => 'Message exceeds maximum length'
        ];
    }

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Messages.json';

    $postData = http_build_query([
        'From' => TWILIO_PHONE_NUMBER,
        'To' => $to,
        'Body' => $message
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("[SMS] CURL error sending to $to: " . $curlError);
        return [
            'success' => false,
            'message_sid' => null,
            'error' => $curlError
        ];
    }

    if ($httpCode !== 201) {
        error_log("[SMS] Twilio API error ($httpCode) sending to $to: " . substr($response, 0, 200));
        return [
            'success' => false,
            'message_sid' => null,
            'error' => "API returned HTTP $httpCode"
        ];
    }

    // Parse response to get message SID
    $responseData = json_decode($response, true);
    $messageSid = $responseData['sid'] ?? null;

    if (!$messageSid) {
        error_log("[SMS] No message SID in response: " . substr($response, 0, 200));
        return [
            'success' => false,
            'message_sid' => null,
            'error' => 'Invalid API response'
        ];
    }

    error_log("[SMS] ✓ Message sent successfully. SID: $messageSid");
    return [
        'success' => true,
        'message_sid' => $messageSid,
        'error' => null
    ];
}

/**
 * Send outbid alert to previous highest bidder
 * @param string $phone Phone number of outbid user
 * @param string $item_title Item title
 * @param int $item_id Item ID
 * @param int|null $user_id Optional user ID for logging
 * @return array Result with success status
 */
function sendOutbidAlert($phone, $item_title, $item_id, $user_id = null) {
    $item_url = APP_DOMAIN . '/item.php?id=' . urlencode($item_id);
    $message = "You've been outbid on '{$item_title}'! Bid again: {$item_url}";

    return sendTwilioSMS($phone, $message, $user_id);
}

/**
 * Send winner notification with checkout link
 * @param string $phone Phone number of winner
 * @param string $item_title Item title
 * @param float $winning_amount Winning bid amount
 * @param string $checkout_url Stripe checkout URL
 * @param int|null $user_id Optional user ID for logging
 * @return array Result with success status
 */
function sendWinnerNotification($phone, $item_title, $winning_amount, $checkout_url, $user_id = null) {
    $message = "Congratulations! You won '{$item_title}' for \${$winning_amount}. "
        . "Complete payment: {$checkout_url}";

    return sendTwilioSMS($phone, $message, $user_id);
}

/**
 * Send verification code via SMS
 * @param string $phone Phone number
 * @param string $code 6-digit code
 * @return array Result with success status
 */
function sendVerificationCode($phone, $code) {
    $message = "Your " . APP_NAME . " verification code is: {$code}. "
        . "This code expires in 15 minutes.";

    return sendTwilioSMS($phone, $message);
}

/**
 * Send auction closure notification to admin (optional)
 * @param string $admin_phone Admin phone number
 * @param int $item_count Number of items closed
 * @param float $total_raised Total amount raised
 * @return array Result with success status
 */
function sendAuctionClosedNotification($admin_phone, $item_count, $total_raised) {
    $message = "Auction complete! Closed {$item_count} items. "
        . "Total raised: \${$total_raised}";

    return sendTwilioSMS($admin_phone, $message);
}

