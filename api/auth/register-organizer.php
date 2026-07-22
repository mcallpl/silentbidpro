<?php
// ============================================================
// API ENDPOINT: Organizer Self-Signup (WEB ONLY)
// POST /api/auth/register-organizer.php
//   { org_name, full_name, email, username, password }
// Creates: organizations row + admin account + manager binding,
// then logs the new organizer in (30-day session cookie).
// Returns: { status, admin:{...}, organization_id }
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/admin-accounts.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid JSON']));
}

// Honeypot: real form leaves this empty; bots that fill it get a fake success.
if (!empty($input['website'])) {
    echo json_encode(['status' => 'ok']);
    exit;
}

$org_name  = trim($input['org_name'] ?? '');
$full_name = trim($input['full_name'] ?? '');
$email     = trim($input['email'] ?? '');
$username  = trim($input['username'] ?? '');
$password  = (string)($input['password'] ?? '');

if ($org_name === '' || mb_strlen($org_name) > 255) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Organization name is required']));
}
if ($full_name === '' || mb_strlen($full_name) > 255) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Your name is required']));
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'A valid email address is required']));
}
if (strlen($username) < 3 || strlen($username) > 60 || !preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Username must be 3-60 characters (letters, numbers, . _ -)']));
}
if (strlen($password) < 8) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters']));
}

// Light abuse brake: cap signups per IP per hour (audit_log is already indexed by time).
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$recent = (int)dbGetValue(
    "SELECT COUNT(*) FROM audit_log
     WHERE event_type = 'ORGANIZER_SIGNUP' AND metadata LIKE ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
    ['%"' . $ip . '"%']
);
if ($ip !== '' && $recent >= 5) {
    http_response_code(429);
    die(json_encode(['status' => 'error', 'message' => 'Too many signups from this address. Please try again later.']));
}

// Username must be free (createAdminAccount also checks; check early for a clear message).
if (dbGetRow("SELECT id FROM admin_accounts WHERE username = ?", [$username])) {
    http_response_code(409);
    die(json_encode(['status' => 'error', 'message' => 'That username is already taken']));
}

// Unique org slug.
$slug = strtolower(trim(preg_replace('/[\s_]+/', '-', preg_replace('/[^\w\s-]/', '', $org_name))));
$slug = substr(trim($slug, '-'), 0, 100) ?: 'org';
if (dbGetRow("SELECT id FROM organizations WHERE slug = ?", [$slug])) {
    $slug .= '-' . substr(bin2hex(random_bytes(4)), 0, 6);
}

$org_id = dbInsert(
    "INSERT INTO organizations (name, slug, contact_email) VALUES (?, ?, ?)",
    [$org_name, $slug, $email]
);
if (!$org_id) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Could not create organization']));
}

$admin_id = createAdminAccount($username, $password, $email, $full_name);
if (!$admin_id) {
    // Roll back the org so a failed signup leaves nothing behind.
    dbQuery("DELETE FROM organizations WHERE id = ?", [(int)$org_id]);
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Could not create account']));
}

// Bind the organizer to their org: default context + manager role.
dbUpdate("UPDATE admin_accounts SET organization_id = ? WHERE id = ?", [(int)$org_id, (int)$admin_id]);
dbInsert(
    "INSERT INTO admin_organizations (admin_id, organization_id, role, created_at, updated_at)
     VALUES (?, ?, 'manager', NOW(), NOW())",
    [(int)$admin_id, (int)$org_id]
);

dbInsert(
    "INSERT INTO audit_log (event_type, metadata, created_at) VALUES (?, ?, NOW())",
    ['ORGANIZER_SIGNUP', json_encode(['admin_id' => (int)$admin_id, 'organization_id' => (int)$org_id, 'ip' => $ip])]
);

// Log them straight in (same 30-day persistent session as login-account.php).
loginAdmin($admin_id);

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'message' => 'Welcome to Silent Bid Pro',
    'organization_id' => (int)$org_id,
    'admin' => [
        'id' => (int)$admin_id,
        'username' => $username,
        'full_name' => $full_name,
    ],
]);
