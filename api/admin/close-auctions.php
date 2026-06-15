<?php
// ============================================================
// API ENDPOINT: Manually Close Expired Auctions
// POST /api/admin/close-auctions.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/auction-closer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

requireAdminAuth();

$result = closeExpiredAuctions();
$closed_count = (int)($result['closed_count'] ?? 0);
$error_count = count($result['errors'] ?? []);
$message = $closed_count === 0
    ? 'No expired auctions to close'
    : "Closed {$closed_count} expired auction(s) and prepared winner payment request(s)";

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'closed' => $closed_count,
    'message' => $message,
    'errors' => $result['errors'] ?? [],
    'error_count' => $error_count
]);
?>
