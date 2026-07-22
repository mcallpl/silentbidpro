<?php
// ============================================================
// API ENDPOINT: Update Event Stripe Settings
// POST /api/admin/update-event-stripe-settings.php
// ============================================================
// Super admin only: Configure per-event Stripe accounts
// Allows each event to use its own Stripe API keys

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid JSON']));
}

$event_id = (int)($input['event_id'] ?? 0);
if (!$event_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Event ID required']));
}

// Verify event exists
$event = dbGetRow("SELECT id, organization_id FROM events WHERE id = ?", [(int)$event_id]);
if (!$event) {
    http_response_code(404);
    die(json_encode(['status' => 'error', 'message' => 'Event not found']));
}

// AUTHORIZATION: super admin, or a manager of the org that owns this event.
// Organizers connect THEIR OWN Stripe account to THEIR OWN event — the
// cross-tenant danger (pointing someone else's event at your Stripe) is
// blocked by the org-scope check. Dies 403 on failure.
requireAdminOrgAccess((int)$event['organization_id']);

// Get input values
$stripe_account_id = trim($input['stripe_account_id'] ?? '');
$stripe_key_publishable = trim($input['stripe_key_publishable'] ?? '');
$stripe_key_secret = trim($input['stripe_key_secret'] ?? '');

// Validate at least one field is provided for clear
$has_keys = !empty($stripe_key_publishable) || !empty($stripe_key_secret) || !empty($stripe_account_id);

// If clearing (all empty), allow it
if (!$has_keys) {
    // Clear Stripe settings for this event (use global settings)
    $success = dbUpdate(
        "UPDATE event_settings
         SET stripe_account_id = NULL, stripe_key_publishable = NULL, stripe_key_secret = NULL
         WHERE event_id = ?",
        [(int)$event_id]
    );

    if ($success) {
        http_response_code(200);
        echo json_encode([
            'status' => 'ok',
            'message' => 'Stripe settings cleared. Event will use global Stripe account.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to update settings']);
    }
    exit;
}

// If setting keys, both must be provided
if (empty($stripe_key_publishable) || empty($stripe_key_secret)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Both publishable and secret keys are required']));
}

// Validate key format (basic check)
if (!preg_match('/^pk_/', $stripe_key_publishable)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid publishable key format (must start with pk_)']));
}

if (!preg_match('/^sk_/', $stripe_key_secret)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid secret key format (must start with sk_)']));
}

// Both keys must be the same mode — a live pk with a test sk (or vice versa)
// makes Stripe.js and the server disagree and every payment fails.
$pk_live = (bool)preg_match('/^pk_live_/', $stripe_key_publishable);
$sk_live = (bool)preg_match('/^sk_live_/', $stripe_key_secret);
if ($pk_live !== $sk_live) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Key mismatch: one key is live mode and the other is test mode. Both must come from the same mode.']));
}

// Verify the secret key actually works before we store it — a typo here
// would silently break every checkout for this event.
require_once __DIR__ . '/../../includes/stripe-utils.php';
$acct = callStripeAPI('/v1/account', [], 'GET', $stripe_key_secret);
if (empty($acct['id'])) {
    $why = $acct['error']['message'] ?? 'Stripe rejected the secret key';
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Could not verify the secret key with Stripe: ' . $why]));
}
// Record the verified Stripe account id (authoritative — not user-supplied).
$stripe_account_id = $acct['id'];

// Check if event_settings record exists
$settings = dbGetRow(
    "SELECT id FROM event_settings WHERE event_id = ?",
    [(int)$event_id]
);

if ($settings) {
    // Update existing settings
    $success = dbUpdate(
        "UPDATE event_settings
         SET stripe_account_id = ?, stripe_key_publishable = ?, stripe_key_secret = ?
         WHERE event_id = ?",
        [$stripe_account_id ?: null, $stripe_key_publishable, $stripe_key_secret, (int)$event_id]
    );
} else {
    // Create new settings
    $success = dbInsert(
        "INSERT INTO event_settings (event_id, stripe_account_id, stripe_key_publishable, stripe_key_secret)
         VALUES (?, ?, ?, ?)",
        [(int)$event_id, $stripe_account_id ?: null, $stripe_key_publishable, $stripe_key_secret]
    );
}

if ($success) {
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Stripe settings updated successfully',
        'event_id' => $event_id,
        'stripe_account_id' => $stripe_account_id
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to update settings']);
}
?>
