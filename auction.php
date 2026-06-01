#!/usr/bin/env php
<?php
// ============================================================
// SILENT BID BUDDY — CLI Administration Tool
// Command-line interface for auction management
// Usage: php auction.php <command> [options]
// ============================================================

// Ensure we're running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bidding.php';
require_once __DIR__ . '/includes/auction-engine.php';
require_once __DIR__ . '/includes/notifications.php';

// ============================================================
// CLI COMMAND DISPATCHER
// ============================================================

class AuctionCLI {
    private $argv = [];

    public function run($argv) {
        $this->argv = $argv;
        $command = $argv[1] ?? 'help';

        switch ($command) {
            case 'item:create':
                $this->itemCreate();
                break;
            case 'qr:generate':
                $this->qrGenerate();
                break;
            case 'monitor:live':
                $this->monitorLive();
                break;
            case 'auction:close':
                $this->auctionClose();
                break;
            case 'help':
            default:
                $this->showHelp();
                break;
        }
    }

    private function hasFlag($flag) {
        return in_array($flag, $this->argv);
    }

    private function itemCreate() {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "CREATE NEW AUCTION ITEM\n";
        echo str_repeat("=", 60) . "\n\n";

        // Prompt for item details
        $title = $this->prompt("Item Title");
        $description = $this->prompt("Description", true);
        $image_url = $this->prompt("Image URL (optional)", false);
        $fair_value = $this->prompt("Fair Market Value (optional)", false);
        $starting_bid = $this->prompt("Starting Bid Amount", false, true);
        $min_increment = $this->prompt("Minimum Bid Increment", false, true, 5);
        $buy_now = $this->prompt("Buy Now Price (optional)", false);

        // Calculate auction end time
        echo "\nAuction Duration:\n";
        $hours = (int)$this->prompt("  Hours", false, true, 2);
        $minutes = (int)$this->prompt("  Minutes", false, true, 0);
        $seconds = (int)$this->prompt("  Seconds", false, true, 0);

        $duration_seconds = ($hours * 3600) + ($minutes * 60) + $seconds;
        $auction_end_time = date('Y-m-d H:i:s', time() + $duration_seconds);

        // Get next item number
        $last_item = dbGetRow(
            "SELECT item_number FROM items ORDER BY item_number DESC LIMIT 1"
        );
        $next_item_number = ($last_item ? $last_item['item_number'] : 0) + 1;

        // Insert item
        $item_id = dbInsert(
            "INSERT INTO items
             (item_number, title, description, image_url, fair_market_value,
              starting_bid, min_increment, buy_now_price, current_high_bid,
              auction_start_time, auction_end_time, is_closed, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                (int)$next_item_number,
                $title,
                $description,
                $image_url,
                !empty($fair_value) ? (float)$fair_value : null,
                (float)$starting_bid,
                (float)$min_increment,
                !empty($buy_now) ? (float)$buy_now : null,
                0.00,
                date('Y-m-d H:i:s'),
                $auction_end_time
            ]
        );

        if ($item_id) {
            echo "\n✓ Item created successfully!\n\n";
            $this->printTable([
                ['Item Number', '#' . $next_item_number],
                ['Item ID', $item_id],
                ['Title', $title],
                ['Starting Bid', '$' . number_format($starting_bid, 2)],
                ['Duration', sprintf('%dh %dm %ds', $hours, $minutes, $seconds)],
                ['Web URL', APP_DOMAIN . '/item.php?id=' . $next_item_number],
                ['QR Code URL', APP_DOMAIN . '/item.php?id=' . $next_item_number]
            ]);
        } else {
            echo "\n✗ Error creating item.\n";
        }
    }

    private function qrGenerate() {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "GENERATE QR CODES\n";
        echo str_repeat("=", 60) . "\n\n";

        // Get all items
        $items = dbGetAll("SELECT id, item_number, title FROM items ORDER BY item_number");

        if (empty($items)) {
            echo "No items found in database.\n";
            return;
        }

        echo "Found " . count($items) . " items. Generating QR codes...\n\n";

        // Create QR codes directory
        if (!is_dir(QR_CODES_DIR)) {
            mkdir(QR_CODES_DIR, 0755, true);
        }

        // Generate QR codes for each item
        $generated = 0;
        $failed = 0;
        foreach ($items as $item) {
            $url = APP_DOMAIN . '/item.php?id=' . $item['item_number'];
            $filename = sprintf(
                '%s/item_%03d_%s.png',
                QR_CODES_DIR,
                $item['item_number'],
                preg_replace('/[^a-z0-9_-]/i', '_', substr($item['title'], 0, 40))
            );

            // Generate QR code using Google Charts API
            $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($url);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $qr_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $qr_data = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200 && $qr_data) {
                if (file_put_contents($filename, $qr_data)) {
                    echo "  ✓ Generated QR code for Item #{$item['item_number']}: {$item['title']}\n";
                    $generated++;
                } else {
                    echo "  ✗ Failed to write QR code file: $filename\n";
                    $failed++;
                }
            } else {
                echo "  ✗ Failed to generate QR code for Item #{$item['item_number']}\n";
                $failed++;
            }
        }

        echo "\n✓ Generated $generated QR codes in " . QR_CODES_DIR . "\n";
        if ($failed > 0) {
            echo "⚠ Failed to generate $failed QR codes\n";
        }
    }

    private function monitorLive() {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "LIVE AUCTION MONITOR\n";
        echo str_repeat("=", 60) . "\n";
        echo "Updating every 2 seconds. Press Ctrl+C to exit.\n\n";

        $iteration = 0;
        while (true) {
            $iteration++;

            // Clear screen (for better UX)
            if ($iteration > 1) {
                system('clear') or system('cls');
            }

            // Get metrics
            $metrics = getLiveMetrics();
            $summary = getAuctionSummary();

            // Display header
            echo str_repeat("=", 60) . "\n";
            echo str_pad("SILENT BID BUDDY — LIVE AUCTION", 60) . "\n";
            echo str_repeat("=", 60) . "\n";
            echo "Last Updated: " . date('Y-m-d H:i:s') . "\n\n";

            // Display key metrics
            echo "KEY METRICS:\n";
            $this->printTable([
                ['Total Active Items', (string)$summary['active_items']],
                ['Active Bidders (1hr)', (string)$metrics['active_bidders']],
                ['Total Bids (1hr)', (string)$metrics['total_bids']],
                ['Estimated Total Raised', '$' . number_format($summary['total_raised'], 2)],
                ['Pending Payments', (string)$summary['pending_payments']],
                ['Completion Rate', $summary['completion_rate'] . '%']
            ]);

            echo "\nHIGH-TRAFFIC ITEMS:\n";
            if (!empty($metrics['high_traffic_items'])) {
                foreach ($metrics['high_traffic_items'] as $idx => $item) {
                    echo sprintf(
                        "  %d. %-40s — %d bids, Current: $%s\n",
                        $idx + 1,
                        substr($item['title'], 0, 40),
                        $item['bid_count'],
                        number_format($item['current_high_bid'], 2)
                    );
                }
            } else {
                echo "  No bids yet.\n";
            }

            echo "\nRECENT ACTIVITY:\n";
            if (!empty($metrics['recent_bids'])) {
                $limit = 5;
                foreach (array_slice($metrics['recent_bids'], 0, $limit) as $bid) {
                    echo sprintf(
                        "  %s — Bid placed: %s on \"%s\" for $%s\n",
                        $bid['created_at'],
                        $bid['full_name'],
                        substr($bid['title'], 0, 30),
                        number_format($bid['bid_amount'], 2)
                    );
                }
            } else {
                echo "  No activity yet.\n";
            }

            echo "\n" . str_repeat("=", 60) . "\n";

            // Sleep before refresh
            sleep(2);
        }
    }

    private function auctionClose() {
        $force = $this->hasFlag('--force');

        if (!$force) {
            echo "\n" . str_repeat("=", 60) . "\n";
            echo "CLOSE AUCTION\n";
            echo str_repeat("=", 60) . "\n\n";
        }

        // Get active items
        $active = dbCount('items', 'is_closed = 0');

        if ($active === 0) {
            if (!$force) {
                echo "No active items to close.\n";
            }
            return;
        }

        if (!$force) {
            echo "This will close $active active auction item(s) and:\n";
            echo "  • Mark all items as closed\n";
            echo "  • Process winners\n";
            echo "  • Send winner notifications\n";
            echo "  • Create payment transactions\n\n";

            $confirm = $this->confirm("Continue?");
            if (!$confirm) {
                echo "Cancelled.\n";
                return;
            }
        }

        if (!$force) {
            echo "\nClosing auctions...\n\n";
        }

        // Close auctions
        $result = closeExpiredAuctions();

        echo "✓ Closed {$result['closed_count']} item(s)\n";

        if (!empty($result['errors'])) {
            echo "\n⚠ Errors encountered:\n";
            foreach ($result['errors'] as $error) {
                echo "  • $error\n";
            }
        }

        echo "\n" . str_repeat("=", 60) . "\n";
    }

    private function prompt($label, $multiline = false, $numeric = false, $default = null) {
        echo $label;
        if ($default !== null) {
            echo " [default: $default]";
        }
        echo ": ";

        $input = trim(fgets(STDIN));

        if (empty($input) && $default !== null) {
            return $default;
        }

        if ($numeric && !is_numeric($input)) {
            echo "Please enter a numeric value.\n";
            return $this->prompt($label, $multiline, $numeric, $default);
        }

        return $input;
    }

    private function confirm($label) {
        echo $label . " (y/n): ";
        $input = strtolower(trim(fgets(STDIN)));
        return $input === 'y' || $input === 'yes';
    }

    private function printTable($rows) {
        $colWidths = [30, 28];

        foreach ($rows as $row) {
            printf("  %-" . $colWidths[0] . "s | %s\n", $row[0], $row[1]);
        }
        echo "\n";
    }

    private function showHelp() {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "SILENT BID BUDDY — Auction Administration CLI\n";
        echo str_repeat("=", 60) . "\n\n";

        echo "AVAILABLE COMMANDS:\n\n";

        echo "  item:create\n";
        echo "    Create a new auction item\n";
        echo "    Usage: php auction.php item:create\n\n";

        echo "  qr:generate\n";
        echo "    Generate QR codes for all items\n";
        echo "    Usage: php auction.php qr:generate\n\n";

        echo "  monitor:live\n";
        echo "    Display live auction monitoring dashboard\n";
        echo "    Usage: php auction.php monitor:live\n\n";

        echo "  auction:close\n";
        echo "    Manually close active auctions\n";
        echo "    Usage: php auction.php auction:close [--force]\n";
        echo "    --force: Skip confirmation prompt (for automated cron jobs)\n\n";

        echo "  help\n";
        echo "    Show this help message\n";
        echo "    Usage: php auction.php help\n\n";

        echo str_repeat("=", 60) . "\n\n";
    }
}

// ============================================================
// RUN CLI
// ============================================================

$cli = new AuctionCLI();
$cli->run($argv);

