<?php
// ============================================================
// SILENT BID BUDDY — Central Configuration
// Loads vault secrets and establishes database connection
// ============================================================

// Load vault secrets (shared across all projects)
$vault_path = dirname(__DIR__) . '/vault/secrets.php';
if (!file_exists($vault_path)) {
    die("ERROR: Vault secrets file not found at $vault_path\n");
}
require_once $vault_path;

// Override with local config if it exists (for server-specific settings)
$local_config_path = __DIR__ . '/config.local.php';
if (file_exists($local_config_path)) {
    require_once $local_config_path;
}

// ============================================================
// DATABASE CONFIGURATION
// ============================================================
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', $vault_db_user ?? 'root');
if (!defined('DB_PASS')) define('DB_PASS', $vault_db_pass ?? '');
if (!defined('DB_NAME')) define('DB_NAME', 'silentbidbuddy');

// ============================================================
// STRIPE CONFIGURATION
// ============================================================
if (!defined('STRIPE_SECRET_KEY')) define('STRIPE_SECRET_KEY', $vault_stripe_secret_key ?? '');
if (!defined('STRIPE_PUBLISHABLE_KEY')) define('STRIPE_PUBLISHABLE_KEY', $vault_stripe_publishable_key ?? '');
if (!defined('STRIPE_WEBHOOK_SECRET')) define('STRIPE_WEBHOOK_SECRET', $vault_stripe_webhook_secrets['silentbidbuddy'] ?? '');

// ============================================================
// TWILIO CONFIGURATION
// ============================================================
if (!defined('TWILIO_ACCOUNT_SID')) define('TWILIO_ACCOUNT_SID', $vault_twilio_sid ?? '');
if (!defined('TWILIO_AUTH_TOKEN')) define('TWILIO_AUTH_TOKEN', $vault_twilio_token ?? '');
if (!defined('TWILIO_PHONE_NUMBER')) define('TWILIO_PHONE_NUMBER', $vault_twilio_phone ?? '');

// ============================================================
// APPLICATION CONFIGURATION
// ============================================================
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 30 * 24 * 60 * 60);
if (!defined('VERIFICATION_CODE_LIFETIME')) define('VERIFICATION_CODE_LIFETIME', 15 * 60);
if (!defined('MAX_VERIFICATION_ATTEMPTS')) define('MAX_VERIFICATION_ATTEMPTS', 5);
if (!defined('RATE_LIMIT_CODES_PER_MINUTE')) define('RATE_LIMIT_CODES_PER_MINUTE', 5);
if (!defined('ANTI_SNIPING_MINUTES')) define('ANTI_SNIPING_MINUTES', 2);

// ============================================================
// ADMIN CONFIGURATION
// ============================================================
if (!defined('ADMIN_SESSION_COOKIE_NAME')) define('ADMIN_SESSION_COOKIE_NAME', 'admin_session_token');
if (!defined('ADMIN_TOKEN')) {
    define('ADMIN_TOKEN', $vault_admin_token ?? '');
    if (empty(ADMIN_TOKEN)) {
        error_log("FATAL: ADMIN_TOKEN not configured in vault/secrets.php");
        die("Configuration Error: Admin token not found. Contact administrator.");
    }
}

// ============================================================
// REBRANDLY CONFIGURATION
// ============================================================
if (!defined('REBRANDLY_API_KEY')) define('REBRANDLY_API_KEY', $vault_rebrandly_api_key ?? '');

if (!defined('UPLOADS_DIR')) define('UPLOADS_DIR', __DIR__ . '/uploads/');
if (!defined('QR_CODES_DIR')) define('QR_CODES_DIR', __DIR__ . '/qr_codes/');

if (!defined('APP_DOMAIN')) define('APP_DOMAIN', getenv('APP_DOMAIN') ?: 'http://localhost');
if (!defined('APP_NAME')) define('APP_NAME', 'Silent Bid Buddy');

// ============================================================
// COOKIE CONFIGURATION (for session persistence)
// ============================================================
if (!defined('COOKIE_DOMAIN')) {
    $cookie_domain = '';
    // Auto-detect domain for cookie persistence
    if (!empty($_SERVER['HTTP_HOST'])) {
        $host = $_SERVER['HTTP_HOST'];
        // Remove port if present
        $host = preg_replace('/:.*$/', '', $host);
        // For real domains (not localhost/IP), prepend dot for subdomain cookies
        if ($host !== 'localhost' && $host !== '127.0.0.1' && !filter_var($host, FILTER_VALIDATE_IP)) {
            $cookie_domain = '.' . $host;
        }
    }
    define('COOKIE_DOMAIN', $cookie_domain);
}

// ============================================================
// DATABASE CONNECTION SINGLETON
// ============================================================
function getDB() {
    static $db = null;

    if ($db === null) {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($db->connect_error) {
            error_log("Database connection failed: " . $db->connect_error);
            die(json_encode([
                'status' => 'error',
                'message' => 'Database connection failed'
            ]));
        }

        // Set charset to UTF-8
        $db->set_charset('utf8mb4');

        // Set timezone to America/Los_Angeles
        $offset = (new DateTime('now', new DateTimeZone('America/Los_Angeles')))->format('P');
        $db->query("SET time_zone = '$offset'");
    }

    return $db;
}

// ============================================================
// ERROR HANDLING
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Ensure logs directory exists
if (!is_dir(__DIR__ . '/logs')) {
    @mkdir(__DIR__ . '/logs', 0755, true);
}

// ============================================================
// SESSION CONFIGURATION
// ============================================================
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path' => '/',
    'domain' => COOKIE_DOMAIN,
    'secure' => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

