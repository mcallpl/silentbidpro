<?php
// ============================================================
// API ENDPOINT: Bidder Logout
// POST /api/auth/logout.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/session-manager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

$token = getSessionToken();
if ($token) {
    destroySession($token);
}

clearSessionCookie(SESSION_COOKIE_NAME);

// Also release the auction this session was locked to, so signing out and
// re-opening an event link cleanly re-pins to that auction.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['bidder_event_id']);

echo json_encode(['status' => 'ok', 'message' => 'Signed out']);
?>
