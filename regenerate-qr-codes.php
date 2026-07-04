<?php
// ============================================================
// REGENERATE QR CODES WITH CORRECT PRODUCTION URL
// Fixes QR codes that were created with old localhost URLs
// Usage: php regenerate-qr-codes.php
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-helpers.php';
require_once __DIR__ . '/includes/rebrandly-utils.php';

// Override APP_DOMAIN for CLI execution
// When running via CLI, detect domain from environment or use production
if (php_sapi_name() === 'cli') {
    // Check if we're on production server (parent directory suggests production path)
    $script_dir = dirname(__FILE__);
    if (strpos($script_dir, '/var/www') !== false) {
        // We're on production server
        if (!defined('APP_DOMAIN_OVERRIDE')) define('APP_DOMAIN_OVERRIDE', 'https://silentbidpro.com');
    }
}

echo "================================\n";
echo "REGENERATING QR CODES\n";
echo "================================\n";
echo "\n";

// Use override domain if set, otherwise use APP_DOMAIN
$domain = defined('APP_DOMAIN_OVERRIDE') ? APP_DOMAIN_OVERRIDE : APP_DOMAIN;

// Get all items
$items = dbGetAll(
    "SELECT id, item_number, title, qr_code_url, short_url FROM items"
);

if (empty($items)) {
    echo "❌ No items found\n";
    exit(1);
}

echo "Found " . count($items) . " items\n";
echo "Using domain: " . $domain . "\n";
echo "\n";

$updated = 0;
$failed = 0;

foreach ($items as $item) {
    $item_id = $item['id'];
    $item_number = $item['item_number'];
    $title = $item['title'];

    echo "Processing Item #{$item_number}: {$title}... ";

    try {
        // Generate new QR code URL with correct domain
        $qr_target_url = $domain . '/item-qr.php?id=' . $item_id;

        // Create short URL
        $short_url = RebrandlyUtils::createShortUrl($qr_target_url, 'Item ' . $item_number . ': ' . $title);

        if ($short_url) {
            // Get QR code
            $qr_code_url = RebrandlyUtils::getQRCode($short_url);

            // Update database
            dbUpdate(
                "UPDATE items SET qr_code_url = ?, short_url = ? WHERE id = ?",
                [$qr_code_url, $short_url, $item_id]
            );

            echo "✅\n";
            echo "  Target: $qr_target_url\n";
            echo "  Short: $short_url\n";
            $updated++;
        } else {
            echo "❌ Failed to create short URL\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n";
echo "================================\n";
echo "SUMMARY\n";
echo "================================\n";
echo "Updated: $updated\n";
echo "Failed: $failed\n";
echo "\n";

if ($failed === 0) {
    echo "✅ All QR codes regenerated with correct production URL!\n";
    echo "QR codes now point to: " . $domain . "\n";
} else {
    echo "⚠️  Some QR codes failed. Check Rebrandly API key.\n";
}

?>
