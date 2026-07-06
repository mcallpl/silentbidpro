<?php
// ============================================================
// API ENDPOINT: Register APNs Device Token
// POST /api/notifications/register-device.php  { token, environment? }
// Upserts the caller's iOS push token so we can send outbid/win alerts.
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

$user = requireAuth();
$user_id = (int)$user['id'];

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$token = trim($input['token'] ?? '');
$environment = ($input['environment'] ?? 'production') === 'sandbox' ? 'sandbox' : 'production';

// APNs device tokens are hex (historically 64 chars; can vary). Be lenient but sane.
if ($token === '' || !preg_match('/^[0-9a-fA-F]{32,200}$/', $token)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid device token']));
}

if (!dbTableExists('device_tokens')) {
    // Migration 010 not applied yet — accept gracefully so the app isn't blocked.
    http_response_code(200);
    die(json_encode(['status' => 'ok', 'message' => 'Registration accepted']));
}

// Upsert: a token maps to whoever last registered it; reactivate on re-register.
dbQuery(
    "INSERT INTO device_tokens (user_id, token, platform, environment, is_active, last_used_at, created_at)
     VALUES (?, ?, 'ios', ?, 1, NOW(), NOW())
     ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), environment = VALUES(environment),
                             is_active = 1, last_used_at = NOW()",
    [$user_id, $token, $environment]
);

http_response_code(200);
echo json_encode(['status' => 'ok', 'message' => 'Device registered']);
