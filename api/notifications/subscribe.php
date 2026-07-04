<?php
// ============================================================
// SILENT BID PRO — Push Notification Subscription
// Save browser subscription endpoint to database
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db-helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Require authentication
$user = requireAuth();
$user_id = (int)$user['id'];

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (empty($data['endpoint']) || empty($data['auth_key']) || empty($data['p256dh_key'])) {
    http_response_code(400);
    die(json_encode([
        'status' => 'error',
        'message' => 'Missing required subscription data'
    ]));
}

$endpoint = (string)$data['endpoint'];
$auth_key = (string)$data['auth_key'];
$p256dh_key = (string)$data['p256dh_key'];
$browser_type = $data['browser_type'] ?? 'unknown';

// Validate endpoint URL
if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    die(json_encode([
        'status' => 'error',
        'message' => 'Invalid endpoint URL'
    ]));
}

try {
    // Check if subscription already exists for this endpoint
    $existing = dbGetRow(
        "SELECT id FROM push_subscriptions WHERE endpoint = ? AND user_id = ?",
        [$endpoint, $user_id]
    );

    if ($existing) {
        // Update existing subscription
        dbUpdate(
            "UPDATE push_subscriptions SET auth_key = ?, p256dh_key = ?, browser_type = ?, is_active = 1, updated_at = NOW()
             WHERE id = ?",
            [$auth_key, $p256dh_key, $browser_type, (int)$existing['id']]
        );
        $subscription_id = $existing['id'];
    } else {
        // Create new subscription
        $subscription_id = dbInsert(
            "INSERT INTO push_subscriptions (user_id, endpoint, auth_key, p256dh_key, browser_type, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())",
            [$user_id, $endpoint, $auth_key, $p256dh_key, $browser_type]
        );
    }

    // Log to audit trail
    dbInsert(
        "INSERT INTO audit_log (event_type, user_id, description, created_at) VALUES (?, ?, ?, NOW())",
        ['PUSH_SUBSCRIBED', $user_id, "Push notification subscription registered via {$browser_type}"]
    );

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'subscription_id' => $subscription_id,
        'message' => 'Push subscription saved'
    ]);
} catch (Exception $e) {
    error_log("Push subscription error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to save subscription'
    ]);
}
