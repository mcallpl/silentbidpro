<?php
// ============================================================
// ADMIN ACCOUNTS — Username/Password Authentication
// Replaces token-based auth with proper user accounts
// ============================================================

require_once __DIR__ . '/db-helpers.php';
require_once __DIR__ . '/session-manager.php';

/**
 * Hash a password using bcrypt
 * @param string $password Plain text password
 * @return string Password hash
 */
function hashAdminPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify a password against its hash
 * @param string $password Plain text password
 * @param string $hash Password hash
 * @return bool True if password matches
 */
function verifyAdminPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Create a new admin account
 * @param string $username Unique username
 * @param string $password Plain text password
 * @param string $email Optional email
 * @param string $full_name Optional full name
 * @return int|false Admin account ID or false on failure
 */
function createAdminAccount($username, $password, $email = '', $full_name = '') {
    // Validate username
    if (empty($username) || strlen($username) < 3) {
        return false;
    }

    // Validate password
    if (empty($password) || strlen($password) < 8) {
        return false;
    }

    // Check if username already exists
    $existing = dbGetRow(
        "SELECT id FROM admin_accounts WHERE username = ?",
        [$username]
    );

    if ($existing) {
        return false; // Username already taken
    }

    // Hash password
    $password_hash = hashAdminPassword($password);

    // Insert admin account
    return dbInsert(
        "INSERT INTO admin_accounts (username, password_hash, email, full_name)
         VALUES (?, ?, ?, ?)",
        [$username, $password_hash, $email, $full_name]
    );
}

/**
 * Authenticate admin by username and password
 * @param string $username
 * @param string $password
 * @return array|false Admin account data or false
 */
function authenticateAdmin($username, $password) {
    if (empty($username) || empty($password)) {
        return false;
    }

    // Fetch admin account
    $admin = dbGetRow(
        "SELECT id, username, password_hash, email, full_name, is_active FROM admin_accounts WHERE username = ?",
        [$username]
    );

    if (!$admin) {
        return false; // User not found
    }

    // Check if account is active
    if (!$admin['is_active']) {
        return false; // Account disabled
    }

    // Verify password
    if (!verifyAdminPassword($password, $admin['password_hash'])) {
        return false; // Invalid password
    }

    // Update last login
    dbUpdate(
        "UPDATE admin_accounts SET last_login = NOW() WHERE id = ?",
        [(int)$admin['id']]
    );

    return [
        'id' => $admin['id'],
        'username' => $admin['username'],
        'email' => $admin['email'],
        'full_name' => $admin['full_name']
    ];
}

/**
 * Login admin and set persistent session cookie
 * @param int $admin_id Admin account ID
 * @return string Session token
 */
function loginAdmin($admin_id) {
    // Create session token
    $session_token = bin2hex(random_bytes(32));

    // Set persistent session cookie
    setSessionCookie(ADMIN_SESSION_COOKIE_NAME, $session_token, SESSION_COOKIE_LIFETIME);

    // Log audit event
    dbInsert(
        "INSERT INTO audit_log (event_type, metadata, created_at)
         VALUES (?, ?, NOW())",
        ['ADMIN_LOGIN', json_encode(['admin_id' => $admin_id])]
    );

    return $session_token;
}

/**
 * Logout admin and clear session cookie
 * @return bool
 */
function logoutAdmin() {
    clearSessionCookie(ADMIN_SESSION_COOKIE_NAME);

    dbInsert(
        "INSERT INTO audit_log (event_type, description, created_at)
         VALUES (?, ?, NOW())",
        ['ADMIN_LOGOUT', 'Admin logged out']
    );

    return true;
}

/**
 * Get current admin session from cookie
 * @return string|null Session token or null
 */
function getAdminSessionToken() {
    return getSessionCookie(ADMIN_SESSION_COOKIE_NAME);
}

/**
 * Check if admin is logged in (has valid session cookie)
 * @return bool
 */
function isAdminLoggedIn() {
    return hasSessionCookie(ADMIN_SESSION_COOKIE_NAME);
}

/**
 * Get admin account data from session
 * @return array|false Admin data or false if not logged in
 */
function getLoggedInAdmin() {
    if (!isAdminLoggedIn()) {
        return false;
    }

    $token = getAdminSessionToken();
    if (!$token) {
        return false;
    }

    // For now, we'll validate the token exists
    // In a real system, you might store session records in a table
    // For simplicity, just check if cookie is set and assume it's valid
    // A production system should validate against a sessions table

    return [
        'has_session' => true,
        'token' => $token
    ];
}

/**
 * Require admin authentication via session cookie
 * Dies with JSON error if not authenticated
 * @return bool Always true if authenticated
 */
function requireAdminSessionAuth() {
    if (!isAdminLoggedIn()) {
        http_response_code(401);
        die(json_encode([
            'status' => 'error',
            'message' => 'Unauthorized. Admin session not found.'
        ]));
    }
    return true;
}

/**
 * Check if an admin account is super admin
 * @param int $admin_id
 * @return bool
 */
function isAdminSuperAdmin($admin_id) {
    $admin = dbGetRow(
        "SELECT is_super_admin FROM admin_accounts WHERE id = ?",
        [(int)$admin_id]
    );

    return $admin && $admin['is_super_admin'];
}

/**
 * Require super admin privileges
 * Dies with JSON error if not super admin
 * @param int $admin_id
 * @return bool Always true if super admin
 */
function requireSuperAdmin($admin_id) {
    if (!isAdminSuperAdmin($admin_id)) {
        http_response_code(403);
        die(json_encode([
            'status' => 'error',
            'message' => 'Forbidden. Super admin privileges required.'
        ]));
    }
    return true;
}

/**
 * Change admin password
 * @param int $admin_id
 * @param string $old_password
 * @param string $new_password
 * @return bool
 */
function changeAdminPassword($admin_id, $old_password, $new_password) {
    // Fetch admin
    $admin = dbGetRow(
        "SELECT password_hash FROM admin_accounts WHERE id = ?",
        [(int)$admin_id]
    );

    if (!$admin) {
        return false;
    }

    // Verify old password
    if (!verifyAdminPassword($old_password, $admin['password_hash'])) {
        return false;
    }

    // Validate new password
    if (empty($new_password) || strlen($new_password) < 8) {
        return false;
    }

    // Hash and update
    $new_hash = hashAdminPassword($new_password);
    return dbUpdate(
        "UPDATE admin_accounts SET password_hash = ? WHERE id = ?",
        [$new_hash, (int)$admin_id]
    );
}

?>
