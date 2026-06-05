<?php
// ============================================================
// API ENDPOINT: Verify Code and Create Session
// POST /api/auth/verify-code.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/session-manager.php';

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
$code = $input['code'] ?? '';
$full_name = $input['full_name'] ?? '';

if (empty($phone) || empty($code) || empty($full_name)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Phone, name, and code are required']));
}

// Normalize phone
$normalized_phone = normalizePhone($phone);
if (!$normalized_phone) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid phone number']));
}

// Verify code
if (!verifyCode($normalized_phone, $code)) {
    http_response_code(401);
    die(json_encode([
        'status' => 'error',
        'message' => 'Invalid or expired verification code'
    ]));
}

// Get or create user
$user_id = getOrCreateUser($normalized_phone, $full_name);

if (!$user_id) {
    http_response_code(500);
    die(json_encode([
        'status' => 'error',
        'message' => 'Failed to create user session'
    ]));
}

// Create session
$session_token = createSession($user_id);

// Set PERSISTENT session cookie - 30 days, works on ALL PHP versions
// This is critical for user experience - users should NOT need to re-verify every visit
$expires = time() + (30 * 24 * 60 * 60); // 30 days
$path = '/';
$domain = COOKIE_DOMAIN ?: ''; // Auto-detected from config.php
$secure = !empty($_SERVER['HTTPS']); // HTTPS only on production
$httponly = true; // Never expose to JavaScript

$cookie_log = '[COOKIE] Headers sent: ' . (headers_sent() ? 'YES - PROBLEM!' : 'NO') . PHP_EOL;
$cookie_log .= '[COOKIE] Setting session cookie: name=' . SESSION_COOKIE_NAME . ', domain=' . $domain . ', path=' . $path . ', expires=' . date('Y-m-d H:i:s', $expires) . PHP_EOL;

$result = false;
if (PHP_VERSION_ID >= 70300) {
    // PHP 7.3+ array syntax
    $result = setcookie(SESSION_COOKIE_NAME, $session_token, [
        'expires' => $expires,
        'path' => $path,
        'domain' => $domain,
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Lax'
    ]);
    $cookie_log .= '[COOKIE] setcookie() returned: ' . ($result ? 'true (success)' : 'false (failed)') . PHP_EOL;
} else {
    // PHP < 7.3 compatible syntax
    $result = setcookie(SESSION_COOKIE_NAME, $session_token, $expires, $path, $domain, $secure, $httponly);
    $cookie_log .= '[COOKIE] setcookie() returned: ' . ($result ? 'true (success)' : 'false (failed)') . PHP_EOL;
}

// Write to log file
@file_put_contents('/var/www/html/silentbidbuddy/logs/session-cookie.log', date('Y-m-d H:i:s') . ' ' . $cookie_log, FILE_APPEND);

// Get user data
$user = dbGetRow(
    "SELECT id, phone_number, full_name FROM users WHERE id = ?",
    [(int)$user_id]
);

// Log audit event
dbInsert(
    "INSERT INTO audit_log (event_type, user_id, description, created_at)
     VALUES (?, ?, ?, NOW())",
    ['LOGIN_SUCCESS', (int)$user_id, 'User logged in via SMS verification']
);

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'message' => 'Login successful',
    'session_token' => $session_token,
    'user' => [
        'id' => $user['id'],
        'phone_number' => $user['phone_number'],
        'full_name' => $user['full_name']
    ]
]);

?>
