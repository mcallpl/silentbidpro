<?php
// ============================================================
// API ENDPOINT: Toggle Favorite
// POST /api/bidding/toggle-favorite.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/favorites.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

if (!favoritesAvailable()) {
    http_response_code(503);
    die(json_encode([
        'status' => 'error',
        'message' => 'Watchlist is not available until the favorites migration is applied'
    ]));
}

$user = requireAuth();
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid JSON']));
}

$item_id = (int)($input['item_id'] ?? 0);
$desired = $input['favorite'] ?? null;

if (!$item_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Item ID required']));
}

if (!dbExists('items', 'id = ?', [$item_id])) {
    http_response_code(404);
    die(json_encode(['status' => 'error', 'message' => 'Item not found']));
}

$is_favorited = isItemFavorited((int)$user['id'], $item_id);
$should_favorite = is_bool($desired) ? $desired : !$is_favorited;

if ($should_favorite) {
    $ok = addFavorite((int)$user['id'], $item_id);
} else {
    $ok = removeFavorite((int)$user['id'], $item_id);
}

if (!$ok && $should_favorite !== $is_favorited) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Could not update watchlist']));
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'item_id' => $item_id,
    'is_favorited' => $should_favorite
]);
?>
