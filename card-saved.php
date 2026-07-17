<?php
// ============================================================
// SILENT BID PRO — Card Setup Return
// Landing page after Stripe Checkout (setup mode). Persists the
// saved payment method immediately (the webhook is the backup),
// then bounces the bidder back to where they were bidding.
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db-helpers.php';
require_once __DIR__ . '/includes/card-on-file.php';

$session_id = (string)($_GET['session_id'] ?? '');
$return = (string)($_GET['return'] ?? 'items.php');

// Same internal-path rule as everywhere else — never redirect off-site.
$return = ltrim($return, '/');
if ($return === '' || preg_match('#^(https?:)?//#i', $return) || preg_match('#^[a-z][a-z0-9+.-]*:#i', $return) || strpos($return, '..') !== false) {
    $return = 'items.php';
}

$saved = false;
if ($session_id !== '') {
    try {
        $saved = saveCardFromSetupSession($session_id);
    } catch (Exception $e) {
        error_log('[CARD-SAVED] finalize failed (webhook will retry): ' . $e->getMessage());
    }
}

$sep = (strpos($return, '?') !== false) ? '&' : '?';
header('Location: ' . $return . ($saved ? $sep . 'card_saved=1' : ''));
exit;
