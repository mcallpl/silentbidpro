<?php
// ============================================================
// API ENDPOINT: Update Event
// POST /api/admin/update-event.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/admin-auth.php';
require_once __DIR__ . '/../../includes/admin-auth-middleware.php';
require_once __DIR__ . '/../../includes/plans.php';

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

$event_id = (int)($input['id'] ?? 0);
if (!$event_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Event ID required']));
}

// Check authorization
if (!$admin['is_super_admin']) {
    $access = checkAdminEventAccess($admin['id'], $event_id);
    if (!$access || $access['role'] !== 'manager') {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Access denied']));
    }
}

// Validate required fields
if (empty($input['name'])) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Event name is required']));
}

if (empty($input['auction_end_time'])) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Auction end time is required']));
}

// Sanitize inputs
$name = trim($input['name']);
$status = $input['status'] ?? 'draft';
$payment_mode = $input['payment_mode'] ?? 'both';
$timezone = $input['timezone'] ?? 'America/Los_Angeles';
$event_date = !empty($input['event_date']) ? $input['event_date'] : null;
$auction_start_time = !empty($input['auction_start_time']) ? $input['auction_start_time'] : null;
$auction_end_time = $input['auction_end_time'];

// Validate enum values
$valid_statuses = ['draft', 'open', 'closed', 'archived'];
$valid_payment_modes = ['combined', 'item', 'both'];

if (!in_array($status, $valid_statuses)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid status']));
}

if (!in_array($payment_mode, $valid_payment_modes)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid payment mode']));
}

// SaaS gating: concurrent active-event limit (Free 1 / Pro 3 / Enterprise ∞).
// Only enforced when this request would ACTIVATE the event (set it to 'open');
// the check excludes this event, so re-saving an already-open event never trips.
if ($status === 'open') {
    $org_id = orgIdForEvent((int)$event_id);
    if ($org_id && !orgCanOpenAnotherEvent($org_id, (int)$event_id)) {
        $plan = getOrgPlan($org_id);
        $limit = planLimit($plan, 'max_active_events');
        http_response_code(403);
        die(json_encode([
            'status'        => 'error',
            'code'          => 'upgrade_required',
            'feature'       => 'max_active_events',
            'current_plan'  => $plan,
            'required_plan' => $plan === 'free' ? 'pro' : 'enterprise',
            'message'       => "Your " . planFeatures($plan)['label'] . " plan allows {$limit} active event"
                             . ($limit === 1 ? '' : 's') . " at a time. Close an event or upgrade on the web to run more concurrently.",
        ]));
    }
}

// Optional per-event behavior switches + fulfillment text (migration 011).
// Only touched when present in the payload so older admin UIs keep working.
$extra_sets = '';
$extra_params = [];
foreach (['require_card_on_bid', 'auto_charge_on_close', 'donations_enabled'] as $flag) {
    if (array_key_exists($flag, $input)) {
        $extra_sets .= ", {$flag} = ?";
        $extra_params[] = !empty($input[$flag]) ? 1 : 0;
    }
}
if (array_key_exists('pickup_instructions', $input)) {
    $extra_sets .= ", pickup_instructions = ?";
    $pi = trim((string)$input['pickup_instructions']);
    $extra_params[] = $pi === '' ? null : $pi;
}

// Update event
$success = dbUpdate(
    "UPDATE events SET name = ?, status = ?, event_date = ?, auction_start_time = ?,
            auction_end_time = ?, payment_mode = ?, timezone = ?{$extra_sets}, updated_at = NOW()
     WHERE id = ?",
    array_merge(
        [$name, $status, $event_date, $auction_start_time, $auction_end_time, $payment_mode, $timezone],
        $extra_params,
        [(int)$event_id]
    )
);

if (!$success) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Failed to update event']));
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'message' => 'Event updated successfully'
]);
?>
