<?php
// ============================================================
// API ENDPOINT: Get Event Stripe Settings
// GET /api/admin/get-event-stripe-settings.php?event_id=1
// ============================================================
// Super admin only: View per-event Stripe configuration

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/admin-auth.php';
require_once __DIR__ . '/../../includes/admin-auth-middleware.php';

header('Content-Type: application/json');

// Require authentication
$admin = getCurrentAdmin();
if (!$admin) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Not authenticated']));
}

$event_id = (int)($_GET['event_id'] ?? 0);
if (!$event_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Event ID required']));
}

// Verify event exists
$event = dbGetRow("SELECT id, name, organization_id FROM events WHERE id = ?", [(int)$event_id]);
if (!$event) {
    http_response_code(404);
    die(json_encode(['status' => 'error', 'message' => 'Event not found']));
}

// AUTHORIZATION: super admin, or a manager of the org that owns this event
// (the secret key is masked in the response either way). Dies 403 on failure.
requireAdminOrgAccess((int)$event['organization_id']);

// Get Stripe settings for this event
$settings = dbGetRow(
    "SELECT stripe_account_id, stripe_key_publishable, stripe_key_secret
     FROM event_settings WHERE event_id = ?",
    [(int)$event_id]
);

if (!$settings) {
    // No custom settings, using global
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'event_id' => $event_id,
        'event_name' => $event['name'],
        'using_custom_keys' => false,
        'message' => 'Using global Stripe account',
        'stripe_account_id' => null,
        'stripe_key_publishable' => null,
        'stripe_key_secret' => null
    ]);
    exit;
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'event_id' => $event_id,
    'event_name' => $event['name'],
    'using_custom_keys' => !empty($settings['stripe_key_publishable']) && !empty($settings['stripe_key_secret']),
    'stripe_account_id' => $settings['stripe_account_id'],
    'stripe_key_publishable' => $settings['stripe_key_publishable'] ? substr($settings['stripe_key_publishable'], 0, 15) . '...' : null,
    'stripe_key_secret' => $settings['stripe_key_secret'] ? substr($settings['stripe_key_secret'], 0, 15) . '...' : null
]);
?>
