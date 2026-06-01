<?php
// ============================================================
// ADMIN AUTHENTICATION — LEGACY COMPATIBILITY
// Token-based auth support for backward compatibility
// New system uses admin_accounts table with username/password
// ============================================================

require_once __DIR__ . '/session-manager.php';

// Legacy token validation (deprecated, kept for backward compatibility)
function validateAdminToken($token) {
    if (empty($token) || !defined('ADMIN_TOKEN')) {
        return false;
    }
    return $token === ADMIN_TOKEN;
}

// Legacy cookie functions (deprecated, use session-manager.php instead)
function setAdminCookie($admin_token) {
    setSessionCookie(ADMIN_SESSION_COOKIE_NAME, $admin_token, SESSION_COOKIE_LIFETIME);
}

function clearAdminCookie() {
    clearSessionCookie(ADMIN_SESSION_COOKIE_NAME);
}

function getAdminToken() {
    return getSessionCookie(ADMIN_SESSION_COOKIE_NAME);
}

function isAdminLoggedIn() {
    return hasSessionCookie(ADMIN_SESSION_COOKIE_NAME);
}

function requireAdminSession() {
    if (!isAdminLoggedIn()) {
        return false;
    }
    return true;
}

?>
