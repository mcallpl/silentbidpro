<?php
// ============================================================
// SILENT BID PRO — Central Configuration
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
// TIMEZONE CONFIGURATION
// ============================================================
// CRITICAL: Set timezone to Pacific Time (Los Angeles) for all PHP operations
date_default_timezone_set('America/Los_Angeles');

// ============================================================
// DATABASE CONFIGURATION
// ============================================================
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', $vault_db_user ?? 'root');
if (!defined('DB_PASS')) define('DB_PASS', $vault_db_pass ?? '');
if (!defined('DB_NAME')) define('DB_NAME', 'silentbidpro');

// ============================================================
// STRIPE CONFIGURATION
// ============================================================
if (!defined('STRIPE_SECRET_KEY')) define('STRIPE_SECRET_KEY', $vault_stripe_secret_key ?? '');
if (!defined('STRIPE_PUBLISHABLE_KEY')) define('STRIPE_PUBLISHABLE_KEY', $vault_stripe_publishable_key ?? '');
if (!defined('STRIPE_WEBHOOK_SECRET')) define('STRIPE_WEBHOOK_SECRET', $vault_stripe_webhook_secrets['SilentBidBuddy'] ?? '');

// Stripe Billing (SaaS subscriptions, WEB ONLY). Recurring Price IDs come from
// the Stripe dashboard (or scripts/setup-billing-prices.php). The billing webhook
// has its OWN signing secret, separate from the auction-payment webhook.
if (!defined('STRIPE_PRICE_PRO')) define('STRIPE_PRICE_PRO', $vault_stripe_price_pro ?? getenv('STRIPE_PRICE_PRO') ?: '');
if (!defined('STRIPE_PRICE_ENTERPRISE')) define('STRIPE_PRICE_ENTERPRISE', $vault_stripe_price_enterprise ?? getenv('STRIPE_PRICE_ENTERPRISE') ?: '');
if (!defined('STRIPE_BILLING_WEBHOOK_SECRET')) define('STRIPE_BILLING_WEBHOOK_SECRET', $vault_stripe_billing_webhook_secret ?? getenv('STRIPE_BILLING_WEBHOOK_SECRET') ?: '');

// ============================================================
// EMAIL — purchase receipts / client welcome notes.
// Primary transport: SendGrid (MAIL_FROM_EMAIL must be a verified
// sender on the account). Fallback: authenticated SMTP when its
// password is present. Sending is a silent no-op with neither.
// ============================================================
if (!defined('SENDGRID_API_KEY')) define('SENDGRID_API_KEY', $vault_SendGrid_api_kay ?? '');
if (!defined('MAIL_FROM_EMAIL')) define('MAIL_FROM_EMAIL', $vault_mail_from_email ?? 'Chip@ChipAndKim.com');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', $vault_mail_from_name ?? 'Silent Bid Pro');
if (!defined('SMTP_HOST')) define('SMTP_HOST', $vault_smtp_host ?? '');
if (!defined('SMTP_PORT')) define('SMTP_PORT', (int)($vault_smtp_port ?? 465));
if (!defined('SMTP_USER')) define('SMTP_USER', $vault_smtp_user ?? '');
if (!defined('SMTP_PASS')) define('SMTP_PASS', $vault_smtp_pass ?? '');

// Demo login for App Store review: Apple's reviewer can't receive our SMS, so a
// designated demo phone + fixed code bypasses Twilio and signs into a pre-seeded
// demo account. Enabled only when both are set (prod config.local.php).
if (!defined('DEMO_LOGIN_PHONE')) define('DEMO_LOGIN_PHONE', $vault_demo_login_phone ?? '');
if (!defined('DEMO_LOGIN_CODE')) define('DEMO_LOGIN_CODE', $vault_demo_login_code ?? '');

// APNs (native iOS push). Set $vault_apns_key_id + $vault_apns_auth_key (the .p8
// PEM contents) to enable; sending is a no-op until then. Team/bundle default to
// the app's known values. Use sandbox for TestFlight/dev builds.
if (!defined('APNS_KEY_ID'))     define('APNS_KEY_ID', $vault_apns_key_id ?? '');
if (!defined('APNS_TEAM_ID'))    define('APNS_TEAM_ID', $vault_apns_team_id ?? 'WSR4HM3CH7');
if (!defined('APNS_BUNDLE_ID'))  define('APNS_BUNDLE_ID', $vault_apns_bundle_id ?? 'com.peoplestar.silentbidpro');
if (!defined('APNS_AUTH_KEY'))   define('APNS_AUTH_KEY', $vault_apns_auth_key ?? '');   // .p8 PEM contents
if (!defined('APNS_USE_SANDBOX')) define('APNS_USE_SANDBOX', $vault_apns_use_sandbox ?? false);

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

// ============================================================
// WEB PUSH (VAPID) CONFIGURATION
// ============================================================
if (!defined('VAPID_PUBLIC_KEY')) define('VAPID_PUBLIC_KEY', $vault_sbb_vapid_public_key ?? '');
if (!defined('VAPID_PRIVATE_KEY')) define('VAPID_PRIVATE_KEY', $vault_sbb_vapid_private_key ?? '');
if (!defined('VAPID_SUBJECT')) define('VAPID_SUBJECT', 'mailto:' . ($vault_contact_email ?? 'noreply@silentbidpro.com'));

if (!defined('UPLOADS_DIR')) define('UPLOADS_DIR', __DIR__ . '/uploads/');
if (!defined('QR_CODES_DIR')) define('QR_CODES_DIR', __DIR__ . '/qr_codes/');

if (!defined('PUBLIC_SITE_URL')) define('PUBLIC_SITE_URL', getenv('PUBLIC_SITE_URL') ?: 'https://silentbidpro.com');
if (!defined('APP_DOMAIN')) {
    // Auto-detect domain from current request or environment variable
    if (!empty($_SERVER['HTTP_HOST'])) {
        $protocol = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
        define('APP_DOMAIN', $protocol . '://' . $_SERVER['HTTP_HOST']);
    } else {
        // CLI/cron: no HTTP_HOST. Fall back to the public site URL, NOT localhost —
        // otherwise cron-generated checkout links in winner SMS point to
        // http://localhost:8000 and are dead for recipients.
        define('APP_DOMAIN', getenv('APP_DOMAIN') ?: PUBLIC_SITE_URL);
    }
}
if (!defined('APP_NAME')) define('APP_NAME', 'Silent Bid Pro');

// Verbose debug logging (full SQL, session-token fragments, cookie dumps). Off by
// default so production logs don't leak sensitive data; enable via the
// SBB_DEBUG_LOG environment variable when diagnosing an issue.
if (!defined('DEBUG_LOG')) define('DEBUG_LOG', getenv('SBB_DEBUG_LOG') === '1');

// ============================================================
// COOKIE CONFIGURATION (for session persistence)
// ============================================================
if (!defined('COOKIE_DOMAIN')) {
    // SECURITY: scope session/admin cookies to the EXACT host only. This server
    // hosts many sibling apps under *.peoplestar.com; the old code set the cookie
    // domain to the parent (".peoplestar.com"), so silentbidpro's session_token
    // and admin_session_token were sent to every sibling subdomain (and any one of
    // them, if compromised, could replay them). An empty COOKIE_DOMAIN pins the
    // cookie to the issuing host, which is what we want.
    define('COOKIE_DOMAIN', '');
}

// ============================================================
// DATABASE CONNECTION SINGLETON
// ============================================================
function getDB() {
    static $db = null;

    if ($db === null) {
        // Connect with CLIENT_FOUND_ROWS so UPDATE affected_rows reports MATCHED
        // rows (not just changed ones). This lets dbUpdate() return a meaningful
        // count for compare-and-swap guards (e.g. the auction closer) while a
        // no-op save of identical data still counts as a matched row, preserving
        // the behavior every existing truthy dbUpdate() caller relied on.
        $db = mysqli_init();
        if (!$db || !@$db->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, null, null, MYSQLI_CLIENT_FOUND_ROWS)) {
            error_log("Database connection failed: " . mysqli_connect_error());
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
