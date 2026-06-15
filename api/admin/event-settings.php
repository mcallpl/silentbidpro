<?php
// ============================================================
// ADMIN API: Event Settings CRUD
// Manage event-specific branding, SMS, and payment settings
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/admin-auth-middleware.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$method = $_SERVER['REQUEST_METHOD'];
$admin = requireAdminAuth();

// Get event_id from query string or request body
$event_id = (int)($_GET['event_id'] ?? json_decode(file_get_contents('php://input'), true)['event_id'] ?? 0);

if (!$event_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Missing event_id']));
}

// Check admin has access to this event
if (!$admin['is_super_admin']) {
    $access = checkAdminEventAccess($admin['id'], $event_id);
    if (!$access) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Forbidden']));
    }
}

// GET: Retrieve event settings
if ($method === 'GET') {
    // Get event details first
    $event = dbGetRow(
        "SELECT id, organization_id, name, event_date FROM events WHERE id = ?",
        [$event_id]
    );

    if (!$event) {
        http_response_code(404);
        die(json_encode(['status' => 'error', 'message' => 'Event not found']));
    }

    // Get settings
    $settings = dbGetRow(
        "SELECT * FROM event_settings WHERE event_id = ?",
        [$event_id]
    );

    // If no settings yet, return defaults
    if (!$settings) {
        $settings = [
            'event_id' => $event_id,
            'logo_url' => null,
            'primary_color' => null,
            'accent_color' => null,
            'sms_enabled' => 1,
            'outbid_sms_template' => null,
            'winner_sms_template' => null,
            'stripe_account_id' => null,
            'stripe_key_publishable' => null,
            'stripe_key_secret' => null
        ];
    }

    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'event' => $event,
        'settings' => $settings
    ]);
    exit;
}

// POST/PUT: Save event settings
if ($method === 'POST' || $method === 'PUT') {
    // Only managers can edit settings
    if (!$admin['is_super_admin']) {
        $access = checkAdminEventAccess($admin['id'], $event_id);
        if (!$access || $access['role'] !== 'manager') {
            http_response_code(403);
            die(json_encode(['status' => 'error', 'message' => 'Forbidden: manager role required']));
        }
    }

    $data = json_decode(file_get_contents('php://input'), true);

    // Validate event exists
    $event = dbGetRow("SELECT id FROM events WHERE id = ?", [$event_id]);
    if (!$event) {
        http_response_code(404);
        die(json_encode(['status' => 'error', 'message' => 'Event not found']));
    }

    // Extract settings (only super admin can toggle SMS)
    $logo_url = $data['logo_url'] ?? null;
    $primary_color = $data['primary_color'] ?? null;
    $accent_color = $data['accent_color'] ?? null;
    $sms_enabled = $data['sms_enabled'] ?? null;
    $outbid_sms_template = $data['outbid_sms_template'] ?? null;
    $winner_sms_template = $data['winner_sms_template'] ?? null;
    $stripe_account_id = $data['stripe_account_id'] ?? null;
    $stripe_key_publishable = $data['stripe_key_publishable'] ?? null;
    $stripe_key_secret = $data['stripe_key_secret'] ?? null;

    // Super admin only: SMS toggle
    if ($sms_enabled !== null && !$admin['is_super_admin']) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Only super admin can toggle SMS']));
    }

    // Check if settings exist
    $existing = dbGetRow(
        "SELECT id FROM event_settings WHERE event_id = ?",
        [$event_id]
    );

    if ($existing) {
        // Update existing
        $updates = [];
        $values = [];

        foreach ([
            'logo_url', 'primary_color', 'accent_color',
            'outbid_sms_template', 'winner_sms_template',
            'stripe_account_id', 'stripe_key_publishable', 'stripe_key_secret'
        ] as $field) {
            if (isset($$field)) {
                $updates[] = "$field = ?";
                $values[] = $$field;
            }
        }

        // Add SMS toggle if super admin and provided
        if ($sms_enabled !== null && $admin['is_super_admin']) {
            $updates[] = "sms_enabled = ?";
            $values[] = (int)$sms_enabled;
        }

        if ($updates) {
            $updates[] = "updated_at = NOW()";
            $values[] = $event_id;

            dbUpdate(
                "UPDATE event_settings SET " . implode(', ', $updates) . " WHERE event_id = ?",
                $values
            );
        }
    } else {
        // Create new settings
        dbInsert(
            "INSERT INTO event_settings
             (event_id, logo_url, primary_color, accent_color, sms_enabled,
              outbid_sms_template, winner_sms_template, stripe_account_id,
              stripe_key_publishable, stripe_key_secret, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $event_id,
                $logo_url,
                $primary_color,
                $accent_color,
                $sms_enabled !== null ? (int)$sms_enabled : 1,
                $outbid_sms_template,
                $winner_sms_template,
                $stripe_account_id,
                $stripe_key_publishable,
                $stripe_key_secret
            ]
        );
    }

    // Log to audit trail
    dbInsert(
        "INSERT INTO audit_log (event_type, user_id, item_id, description, created_at)
         VALUES (?, ?, ?, ?, NOW())",
        ['EVENT_SETTINGS_UPDATED', $admin['id'], $event_id, 'Event settings updated']
    );

    // Get updated settings
    $settings = dbGetRow(
        "SELECT * FROM event_settings WHERE event_id = ?",
        [$event_id]
    );

    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Event settings saved',
        'settings' => $settings
    ]);
    exit;
}

// DELETE: Remove event settings (reset to defaults)
if ($method === 'DELETE') {
    // Only managers can delete settings
    if (!$admin['is_super_admin']) {
        $access = checkAdminEventAccess($admin['id'], $event_id);
        if (!$access || $access['role'] !== 'manager') {
            http_response_code(403);
            die(json_encode(['status' => 'error', 'message' => 'Forbidden']));
        }
    }

    dbUpdate(
        "DELETE FROM event_settings WHERE event_id = ?",
        [$event_id]
    );

    // Log to audit trail
    dbInsert(
        "INSERT INTO audit_log (event_type, user_id, item_id, description, created_at)
         VALUES (?, ?, ?, ?, NOW())",
        ['EVENT_SETTINGS_RESET', $admin['id'], $event_id, 'Event settings reset to defaults']
    );

    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Event settings reset to defaults']);
    exit;
}

// Method not allowed
http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
