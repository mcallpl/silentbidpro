<?php
// ============================================================
// SESSION MANAGER — Robust Cookie Handling for All PHP Versions
// Handles both user and admin session persistence
// ============================================================

define('SESSION_COOKIE_NAME', 'session_token');
define('ADMIN_SESSION_COOKIE_NAME', 'admin_session_token');
define('SESSION_COOKIE_LIFETIME', 30 * 24 * 60 * 60); // 30 days

/**
 * Set a secure, persistent session cookie (works on ALL PHP versions)
 * @param string $name Cookie name
 * @param string $value Cookie value
 * @param int $lifetime Lifetime in seconds
 * @return bool Success
 */
function setSessionCookie($name, $value, $lifetime = SESSION_COOKIE_LIFETIME) {
    $expires = time() + $lifetime;
    $path = '/';
    $domain = COOKIE_DOMAIN ?: '';
    $secure = !empty($_SERVER['HTTPS']);
    $httponly = true;

    // PHP 7.3+ supports array syntax, older versions use positional args
    if (PHP_VERSION_ID >= 70300) {
        return setcookie($name, $value, [
            'expires' => $expires,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => 'Lax'
        ]);
    } else {
        // For PHP < 7.3: use positional parameters
        return setcookie($name, $value, $expires, $path, $domain, $secure, $httponly);
    }
}

/**
 * Clear a session cookie (works on ALL PHP versions)
 * @param string $name Cookie name
 * @return bool Success
 */
function clearSessionCookie($name) {
    $path = '/';
    $domain = COOKIE_DOMAIN ?: '';
    $secure = !empty($_SERVER['HTTPS']);
    $httponly = true;

    if (PHP_VERSION_ID >= 70300) {
        return setcookie($name, '', [
            'expires' => time() - 3600,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => 'Lax'
        ]);
    } else {
        return setcookie($name, '', time() - 3600, $path, $domain, $secure, $httponly);
    }
}

/**
 * Get cookie value (safely)
 * @param string $name Cookie name
 * @return string|null Cookie value or null
 */
function getSessionCookie($name) {
    return $_COOKIE[$name] ?? null;
}

/**
 * Check if a session cookie exists and is not empty
 * @param string $name Cookie name
 * @return bool
 */
function hasSessionCookie($name) {
    return !empty($_COOKIE[$name]);
}

?>
