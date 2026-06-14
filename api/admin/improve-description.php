<?php
// ============================================================
// API ENDPOINT: Improve Item Description
// POST /api/admin/improve-description.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/item-description-enhancer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

requireAdminAuth();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid JSON']));
}

$title = trim((string)($input['title'] ?? ''));
$description = trim((string)($input['description'] ?? ''));

try {
    $enhanced = enhanceAuctionDescription($title, $description, [
        'fair_market_value' => $input['fair_market_value'] ?? null,
        'starting_bid' => $input['starting_bid'] ?? null,
        'buy_now_price' => $input['buy_now_price'] ?? null,
    ]);

    echo json_encode([
        'status' => 'ok',
        'description' => $enhanced['description'],
        'image_prompt' => $enhanced['image_prompt'],
        'catalog_summary' => $enhanced['summary'],
        'message' => 'Description improved. Please review before saving.'
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (Exception $e) {
    error_log('Description improvement failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to improve description right now.']);
}
?>
