<?php
// ============================================================
// SILENT BID BUDDY — Push Notification Unsubscription
// Mark subscription as inactive
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

// Validate: need either endpoint or subscription_id
if (empty($data['endpoint']) && empty($data['subscription_id'])) {
    http_response_code(400);
    die(json_encode([
        'status' => 'error',
        'message' => 'Must provide endpoint or subscription_id'
    ]));
}

try {
    if (!empty($data['endpoint'])) {
        // Unsubscribe by endpoint
        dbUpdate(
            "UPDATE push_subscriptions SET is_active = 0, updated_at = NOW()
             WHERE endpoint = ? AND user_id = ?",
            [$data['endpoint'], $user_id]
        );
    } else {
        // Unsubscribe by subscription_id
        dbUpdate(
            "UPDATE push_subscriptions SET is_active = 0, updated_at = NOW()
             WHERE id = ? AND user_id = ?",
            [(int)$data['subscription_id'], $user_id]
        );
    }

    // Log to audit trail
    dbInsert(
        "INSERT INTO audit_log (event_type, user_id, description, created_at) VALUES (?, ?, ?, NOW())",
        ['PUSH_UNSUBSCRIBED', $user_id, 'Push notification subscription deactivated']
    );

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Unsubscribed from push notifications'
    ]);
} catch (Exception $e) {
    error_log("Push unsubscription error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to unsubscribe'
    ]);
}
