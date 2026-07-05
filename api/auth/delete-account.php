<?php
// ============================================================
// API ENDPOINT: Delete My Account (Apple App Store Guideline 5.1.1(v))
// POST /api/auth/delete-account.php
//
// Actually deletes the account: removes all PII and every credential/session so
// the person can no longer sign in as themselves, and any re-registration with
// their phone creates a fresh account. Bids/transactions are retained but fully
// anonymized (auction/financial record integrity) — no personal data remains.
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

$user = requireAuth();
$user_id = (int)$user['id'];

$db = getDB();
$db->begin_transaction();
try {
    // Remove every credential / personal-data row tied to the account.
    dbDelete("DELETE FROM sessions WHERE user_id = ?", [$user_id]);
    if (dbTableExists('favorites'))          dbDelete("DELETE FROM favorites WHERE user_id = ?", [$user_id]);
    if (dbTableExists('push_subscriptions')) dbDelete("DELETE FROM push_subscriptions WHERE user_id = ?", [$user_id]);
    if (dbTableExists('verification_codes')) {
        // verification_codes key on phone, not user — scrub by the user's phone.
        $phone = dbGetValue("SELECT phone_number FROM users WHERE id = ?", [$user_id]);
        if ($phone) dbDelete("DELETE FROM verification_codes WHERE phone_number = ?", [$phone]);
    }

    // Anonymize the user record: strip all PII, free up the phone for re-use.
    $placeholder = 'deleted_' . $user_id . '_' . substr(md5(uniqid('', true)), 0, 8);
    $sets = ["phone_number = ?", "full_name = 'Deleted user'"];
    $params = [$placeholder];
    if (dbColumnExists('users', 'email'))              $sets[] = "email = NULL";
    if (dbColumnExists('users', 'stripe_customer_id')) $sets[] = "stripe_customer_id = NULL";
    if (dbColumnExists('users', 'is_active'))          $sets[] = "is_active = 0";
    $params[] = $user_id;
    dbUpdate("UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?", $params);

    if (dbTableExists('audit_log')) {
        dbInsert("INSERT INTO audit_log (event_type, user_id, description, created_at)
                  VALUES (?, ?, ?, NOW())",
                 ['ACCOUNT_DELETED', $user_id, 'Bidder deleted their account (PII scrubbed)']);
    }

    $db->commit();
} catch (\Throwable $e) {
    $db->rollback();
    error_log('[DELETE ACCOUNT] ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Could not delete your account. Please try again.']));
}

http_response_code(200);
echo json_encode(['status' => 'ok', 'message' => 'Your account has been deleted.']);
