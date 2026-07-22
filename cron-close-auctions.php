<?php
// ============================================================
// CRON JOB: Close Expired Auctions
// Run every minute via cron: * * * * * cd /var/www/html/silentbidpro && php cron-close-auctions.php
// ============================================================

// CLI ONLY — closing triggers winner card charges; an unauthenticated HTTP
// request must never be able to invoke it (standing rule since the 2026-07-04
// incident: operational scripts refuse the web).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-helpers.php';
require_once __DIR__ . '/includes/auction-closer.php';

// Close expired auctions
$result = closeExpiredAuctions();
$closed_count = (int)($result['closed_count'] ?? 0);
$error_count = count($result['errors'] ?? []);
$message = $closed_count === 0
    ? 'No expired auctions to close'
    : "Closed {$closed_count} expired auction(s)";

if ($error_count > 0) {
    $message .= " with {$error_count} error(s)";
}

// Log the result
$log_message = '[CRON] ' . date('Y-m-d H:i:s') . ' - ' . $message;
error_log($log_message);

// Write to file for verification
@file_put_contents(
    __DIR__ . '/logs/auction-closer.log',
    $log_message . PHP_EOL,
    FILE_APPEND
);

echo $message;
?>
