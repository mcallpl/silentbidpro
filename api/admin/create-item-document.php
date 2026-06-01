<?php
// ============================================================
// API ENDPOINT: Create/Regenerate Item Document
// POST /api/admin/create-item-document.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/pdf-generator.php';
require_once __DIR__ . '/../../includes/rebrandly-utils.php';

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

$item_id = (int)($input['item_id'] ?? 0);
if (!$item_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'item_id is required']));
}

// Verify item exists
$item = dbGetRow("SELECT * FROM items WHERE id = ?", [$item_id]);
if (!$item) {
    http_response_code(404);
    die(json_encode(['status' => 'error', 'message' => 'Item not found']));
}

// Validate required fields
$required = ['title', 'starting_bid', 'min_increment'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => "$field is required for PDF generation"]));
    }
}

// Generate QR code if it doesn't exist
if (empty($item['qr_code_url']) || empty($item['short_url'])) {
    try {
        $qr_target_url = APP_DOMAIN . '/item-qr.php?id=' . $item_id;
        $short_url = RebrandlyUtils::createShortUrl($qr_target_url, 'Item ' . $item['item_number'] . ': ' . $item['title']);

        if ($short_url) {
            $qr_code_url = RebrandlyUtils::getQRCode($short_url);

            // Store QR code URLs in database
            dbUpdate(
                "UPDATE items SET qr_code_url = ?, short_url = ? WHERE id = ?",
                [$qr_code_url, $short_url, $item_id]
            );

            $item['qr_code_url'] = $qr_code_url;
            $item['short_url'] = $short_url;
        } else {
            http_response_code(500);
            die(json_encode(['status' => 'error', 'message' => 'Failed to generate QR code. Please try again.']));
        }
    } catch (Exception $e) {
        error_log("Error generating QR code: " . $e->getMessage());
        http_response_code(500);
        die(json_encode(['status' => 'error', 'message' => 'Error generating QR code: ' . $e->getMessage()]));
    }
}

// Use database values, not form values - ensures PDF always matches saved data
$item_data = [
    'id' => $item['id'],
    'item_number' => $item['item_number'],
    'title' => $item['title'],
    'description' => $item['description'] ?? '',
    'image_url' => $item['image_url'] ?? '',
    'fair_market_value' => $item['fair_market_value'],
    'starting_bid' => $item['starting_bid'],
    'min_increment' => $item['min_increment'],
    'buy_now_price' => $item['buy_now_price'],
    'auction_duration_seconds' => $input['auction_duration_seconds'] ?? 0
];

try {
    // Generate document
    $pdf_gen = new ItemPDFGenerator($item_data);
    $pdf_gen->generate($item['short_url']);

    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Document created successfully',
        'document_url' => $pdf_gen->getDocumentPath()
    ]);
} catch (Exception $e) {
    error_log("Error creating document: " . $e->getMessage());
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Error creating document: ' . $e->getMessage()]));
}

?>
