<?php
// ============================================================
// API ENDPOINT: Send Verification Code
// POST /api/auth/send-code.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notifications.php';

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid JSON']));
}

$phone = $input['phone'] ?? '';
if (empty($phone)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Phone number required']));
}

// Normalize phone
$normalized_phone = normalizePhone($phone);
if (!$normalized_phone) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid phone number format']));
}

// Check rate limit
if (isPhoneRateLimited($normalized_phone)) {
    http_response_code(429);
    die(json_encode([
        'status' => 'error',
        'message' => 'Too many requests. Please wait a minute before requesting another code.'
    ]));
}

// Create verification code
$code = createVerificationCode($normalized_phone);

// Send via Twilio
$sms_sent = sendVerificationCode($normalized_phone, $code);

if (!$sms_sent) {
    http_response_code(500);
    die(json_encode([
        'status' => 'error',
        'message' => 'Failed to send verification code. Please try again.'
    ]));
}

// Log audit event
dbInsert(
    "INSERT INTO audit_log (event_type, description, created_at)
     VALUES (?, ?, NOW())",
    ['CODE_SENT', 'Verification code sent to ' . $normalized_phone]
);

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'message' => 'Verification code sent. Check your SMS.',
    'phone_masked' => substr($normalized_phone, 0, 4) . '****' . substr($normalized_phone, -3)
]);

?>
