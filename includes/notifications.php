<?php
// ============================================================
// NOTIFICATIONS MODULE
// Twilio SMS integration for alerts and notifications
// ============================================================

require_once __DIR__ . '/../config.php';

/**
 * Send SMS via Twilio REST API
 * @param string $to Recipient phone number (+1XXXXXXXXXX format)
 * @param string $message Message body (max 160 chars for best delivery)
 * @return bool Success/failure
 */
function sendTwilioSMS($to, $message) {
    if (empty(TWILIO_ACCOUNT_SID) || empty(TWILIO_AUTH_TOKEN)) {
        error_log("Twilio credentials not configured");
        return false;
    }

    // Ensure message is not too long
    if (strlen($message) > 1600) {
        error_log("SMS message too long: " . strlen($message) . " chars");
        return false;
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
    curl_close($ch);

    if ($httpCode !== 201) {
        error_log("Twilio API error ($httpCode): " . $response);
        return false;
    }

    return true;
}

/**
 * Send outbid alert to previous highest bidder
 * @param string $phone Phone number of outbid user
 * @param string $item_title Item title
 * @param int $item_id Item ID
 * @return bool
 */
function sendOutbidAlert($phone, $item_title, $item_id) {
    $item_url = APP_DOMAIN . '/item.php?id=' . urlencode($item_id);
    $message = "You've been outbid on '{$item_title}'! Bid again: {$item_url}";

    return sendTwilioSMS($phone, $message);
}

/**
 * Send winner notification with checkout link
 * @param string $phone Phone number of winner
 * @param string $item_title Item title
 * @param float $winning_amount Winning bid amount
 * @param string $checkout_url Stripe checkout URL
 * @return bool
 */
function sendWinnerNotification($phone, $item_title, $winning_amount, $checkout_url) {
    $message = "Congratulations! You won '{$item_title}' for \${$winning_amount}. "
        . "Complete payment: {$checkout_url}";

    return sendTwilioSMS($phone, $message);
}

/**
 * Send verification code via SMS
 * @param string $phone Phone number
 * @param string $code 6-digit code
 * @return bool
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
 * @return bool
 */
function sendAuctionClosedNotification($admin_phone, $item_count, $total_raised) {
    $message = "Auction complete! Closed {$item_count} items. "
        . "Total raised: \${$total_raised}";

    return sendTwilioSMS($admin_phone, $message);
}

