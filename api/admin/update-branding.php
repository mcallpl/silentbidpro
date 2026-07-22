<?php
// ============================================================
// ADMIN API: Update Event Branding
// Handles organization branding and event details updates
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/admin-auth.php';
require_once __DIR__ . '/../../includes/admin-auth-middleware.php'; // requireAdminOrgAccess/EventAccess
require_once __DIR__ . '/../../includes/session-manager.php';

header('Content-Type: application/json');

// Check authentication
if (!isAdminLoggedIn()) {
    error_log('[BRANDING API] ❌ Unauthorized - Admin not logged in');
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized. Admin session required.']));
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

error_log('[BRANDING API] Action: ' . $action);

switch ($action) {
    case 'update_organization':
        handleUpdateOrganization($input);
        break;
    case 'update_event':
        handleUpdateEvent($input);
        break;
    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

/**
 * Update organization branding
 */
function handleUpdateOrganization($input) {
    $org_id = (int)($input['organization_id'] ?? 0);
    if (!$org_id) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'organization_id is required']));
    }

    // AUTHORIZATION: this rewrites org branding that propagates to every event in
    // the org (and the live public site). Without this check any logged-in admin
    // could pass another tenant's organization_id and deface it. Super admin or a
    // manager of that org only.
    requireAdminOrgAccess($org_id);

    // Validate organization exists
    $org = dbGetRow("SELECT id FROM organizations WHERE id = ?", [$org_id]);
    if (!$org) {
        http_response_code(404);
        die(json_encode(['status' => 'error', 'message' => 'Organization not found']));
    }

    // Validate inputs
    $name = trim($input['name'] ?? '');
    $logo_url = trim($input['logo_url'] ?? '');
    $contact_email = trim($input['contact_email'] ?? '');
    $brand_primary = trim($input['brand_primary'] ?? '');
    $brand_accent = trim($input['brand_accent'] ?? '');
    // Secondary/text colors are optional; they live only in event_branding.
    $secondary_color = trim($input['secondary_color'] ?? '');
    $text_color = trim($input['text_color'] ?? '');

    if (empty($name)) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Organization name is required']));
    }

    // Validate hex colors (primary/accent required; secondary/text optional)
    if (!isValidHexColor($brand_primary) || !isValidHexColor($brand_accent)
        || ($secondary_color !== '' && !isValidHexColor($secondary_color))
        || ($text_color !== '' && !isValidHexColor($text_color))) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Invalid hex color format. Use format: #RRGGBB']));
    }

    // Validate email if provided
    if (!empty($contact_email) && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Invalid email address']));
    }

    // Validate URL if provided
    if (!empty($logo_url) && !isValidUrl($logo_url)) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Invalid logo URL']));
    }

    // Update organization
    $result = dbUpdate(
        "UPDATE organizations
         SET name = ?,
             brand_primary = ?,
             brand_accent = ?,
             logo_url = ?,
             contact_email = ?,
             updated_at = NOW()
         WHERE id = ?",
        [$name, $brand_primary, $brand_accent, $logo_url, $contact_email, $org_id]
    );

    if ($result === false) {
        error_log('[BRANDING API] ❌ Database error updating organization');
        http_response_code(500);
        die(json_encode(['status' => 'error', 'message' => 'Failed to update organization']));
    }

    // CRITICAL: the public site renders its CSS from the per-event branding
    // (event_branding table, with the events.* columns as fallback) — NOT from
    // organizations.brand_*. Without this propagation, saving branding here
    // updated a table nothing reads, so colors never actually changed on the
    // live site. Sync the org-level primary/accent/logo/name down to every event
    // in this organization so the change is visible.
    // Only overwrite secondary/text when provided; otherwise keep existing values.
    $sec = $secondary_color !== '' ? $secondary_color : null;
    $txt = $text_color !== '' ? $text_color : null;

    $org_events = dbGetAll("SELECT id FROM events WHERE organization_id = ?", [$org_id]);
    foreach ($org_events as $ev) {
        $eid = (int)$ev['id'];
        dbQuery(
            "INSERT INTO event_branding
                (event_id, primary_color, accent_color, secondary_color, text_color, organization_name, organization_logo_url)
             VALUES (?, ?, ?, COALESCE(?, '#f4f7f2'), COALESCE(?, '#172235'), ?, ?)
             ON DUPLICATE KEY UPDATE
                primary_color = VALUES(primary_color),
                accent_color = VALUES(accent_color),
                secondary_color = COALESCE(?, secondary_color),
                text_color = COALESCE(?, text_color),
                organization_name = VALUES(organization_name),
                organization_logo_url = VALUES(organization_logo_url)",
            [$eid, $brand_primary, $brand_accent, $sec, $txt, $name, ($logo_url !== '' ? $logo_url : null), $sec, $txt]
        );
        // Keep the events.* fallback columns consistent too.
        dbUpdate(
            "UPDATE events SET primary_color = ?, accent_color = ?,
                secondary_color = COALESCE(?, secondary_color), text_color = COALESCE(?, text_color),
                organization_name = ?, organization_logo_url = ? WHERE id = ?",
            [$brand_primary, $brand_accent, $sec, $txt, $name, ($logo_url !== '' ? $logo_url : null), $eid]
        );
    }

    error_log('[BRANDING API] ✓ Organization ' . $org_id . ' branding updated + propagated to ' . count($org_events) . ' event(s)');

    echo json_encode([
        'status' => 'ok',
        'message' => 'Organization branding updated successfully',
        'data' => [
            'organization_id' => $org_id,
            'name' => $name,
            'brand_primary' => $brand_primary,
            'brand_accent' => $brand_accent,
            'logo_url' => $logo_url
        ]
    ]);
}

/**
 * Update event details
 */
function handleUpdateEvent($input) {
    $event_id = (int)($input['event_id'] ?? 0);
    $org_id = (int)($input['organization_id'] ?? 0);

    if (!$org_id) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'organization_id is required']));
    }

    // AUTHORIZATION: only a super admin or a manager of the target org may edit
    // its events (prevents cross-tenant event edits via a supplied organization_id).
    requireAdminOrgAccess($org_id);

    // Validate organization exists
    $org = dbGetRow("SELECT id FROM organizations WHERE id = ?", [$org_id]);
    if (!$org) {
        http_response_code(404);
        die(json_encode(['status' => 'error', 'message' => 'Organization not found']));
    }

    // Validate inputs
    $event_name = trim($input['name'] ?? '');
    $event_date = trim($input['event_date'] ?? '');
    $event_location = trim($input['event_location'] ?? '');
    $event_description = trim($input['description'] ?? '');

    if (empty($event_name)) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Event name is required']));
    }

    if (!empty($event_date) && !isValidDate($event_date)) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Invalid date format. Use YYYY-MM-DD']));
    }

    if ($event_id) {
        // Update existing event
        $event = dbGetRow("SELECT id FROM events WHERE id = ? AND organization_id = ?", [$event_id, $org_id]);
        if (!$event) {
            http_response_code(404);
            die(json_encode(['status' => 'error', 'message' => 'Event not found']));
        }

        $result = dbUpdate(
            "UPDATE events
             SET name = ?,
                 event_date = ?,
                 updated_at = NOW()
             WHERE id = ?",
            [$event_name, $event_date ?: null, $event_id]
        );

        if ($result === false) {
            error_log('[BRANDING API] ❌ Database error updating event');
            http_response_code(500);
            die(json_encode(['status' => 'error', 'message' => 'Failed to update event']));
        }

        // Optional fundraising goal (migration 012; guarded so pre-migration DBs keep working).
        if (array_key_exists('fundraising_goal', $input) && dbColumnExists('events', 'fundraising_goal')) {
            $goal_raw = trim((string)$input['fundraising_goal']);
            $goal_val = $goal_raw === '' ? null : (float)str_replace([',', '$'], '', $goal_raw);
            if ($goal_val !== null && ($goal_val < 0 || $goal_val > 999999999)) {
                http_response_code(400);
                die(json_encode(['status' => 'error', 'message' => 'Fundraising goal must be between 0 and 999,999,999']));
            }
            dbUpdate("UPDATE events SET fundraising_goal = ? WHERE id = ?", [$goal_val, $event_id]);
        }

        error_log('[BRANDING API] ✓ Event ' . $event_id . ' updated successfully');

        echo json_encode([
            'status' => 'ok',
            'message' => 'Event updated successfully',
            'data' => [
                'event_id' => $event_id,
                'name' => $event_name,
                'event_date' => $event_date
            ]
        ]);
    } else {
        // Create new event
        if (empty($event_date)) {
            http_response_code(400);
            die(json_encode(['status' => 'error', 'message' => 'Event date is required for new events']));
        }

        // Generate slug from event name
        $slug = generateSlug($event_name);

        // Check for duplicate slug
        $existing = dbGetRow(
            "SELECT id FROM events WHERE organization_id = ? AND slug = ?",
            [$org_id, $slug]
        );
        if ($existing) {
            $slug = $slug . '-' . uniqid();
        }

        // Set default auction times
        $event_datetime = new DateTime($event_date . ' 18:00:00', new DateTimeZone('America/Los_Angeles'));
        $auction_start = $event_datetime->format('Y-m-d H:i:s');
        $auction_end = $event_datetime->modify('+2 hours')->format('Y-m-d H:i:s');

        $new_event_id = dbInsert(
            "INSERT INTO events (organization_id, name, slug, event_date, auction_start_time, auction_end_time, status, timezone)
             VALUES (?, ?, ?, ?, ?, ?, 'draft', 'America/Los_Angeles')",
            [$org_id, $event_name, $slug, $event_date, $auction_start, $auction_end]
        );

        if ($new_event_id === false) {
            error_log('[BRANDING API] ❌ Database error creating event');
            http_response_code(500);
            die(json_encode(['status' => 'error', 'message' => 'Failed to create event']));
        }
        error_log('[BRANDING API] ✓ New event ' . $new_event_id . ' created successfully');

        echo json_encode([
            'status' => 'ok',
            'message' => 'Event created successfully',
            'data' => [
                'event_id' => $new_event_id,
                'name' => $event_name,
                'event_date' => $event_date,
                'slug' => $slug
            ]
        ]);
    }
}

/**
 * Validate hex color format (3- or 6-digit).
 * NOTE: defined unconditionally so PHP hoists it (it is called earlier in this
 * file). This endpoint is a standalone entry point that includes no other file
 * defining isValidHexColor(), so there is no redeclaration risk.
 */
function isValidHexColor($color) {
    return preg_match('/^#[0-9A-Fa-f]{3}(?:[0-9A-Fa-f]{3})?$/', (string)$color) === 1;
}

/**
 * Validate URL format
 */
function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Validate date format (YYYY-MM-DD)
 */
function isValidDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Generate URL-friendly slug from text
 */
function generateSlug($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^\w\s-]/', '', $text);
    $text = preg_replace('/[\s_]+/', '-', $text);
    $text = preg_replace('/^-+|-+$/', '', $text);
    return substr($text, 0, 120);
}

?>
