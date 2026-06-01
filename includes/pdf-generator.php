<?php
// ============================================================
// PDF GENERATOR — Enterprise-Grade Auction Catalog
// Professional $100M+ Marketing Firm Quality
// ============================================================

require_once __DIR__ . '/qr-code-generator.php';

class ItemPDFGenerator {
    private $item;
    private $shortUrl;

    public function __construct($item) {
        $this->item = $item;
    }

    public function generate($shortUrl, $qrCodeUrl = null) {
        $this->shortUrl = $shortUrl;
        $html = $this->buildHTML();

        $filename = $this->getFilename();
        file_put_contents($filename, $html);

        return $filename;
    }

    private function buildHTML() {
        $item = $this->item;
        $lotNumber = (int)$item['item_number'];
        $title = htmlspecialchars($item['title']);
        $image = htmlspecialchars($item['image_url'] ?? '');
        $startBid = '$' . number_format((float)$item['starting_bid'], 0);
        $fmv = $item['fair_market_value'] ? '$' . number_format((float)$item['fair_market_value'], 0) : 'N/A';
        $increment = '$' . number_format((float)($item['min_increment'] ?? 50), 0);
        $timeRemaining = $this->getTimeRemaining();

        // CRITICAL: Always use production domain for QR codes (users access via phone)
        // Never use localhost - it won't work when scanned by phones
        $productionDomain = 'https://silentbidbuddy.peoplestar.com';
        $bidUrl = $this->shortUrl ?? ($productionDomain . '/item.php?id=' . (int)$item['item_number']);
        $url = htmlspecialchars($bidUrl);
        $qrUrl = QRCodeGenerator::getQRUrl($bidUrl);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LOT {$lotNumber} - {$title}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page {
            size: letter;
            margin: 0;
            padding: 0;
        }

        html, body {
            width: 8.5in;
            height: 11in;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            background: white;
            color: #1a1a1a;
        }

        .container {
            width: 100%;
            height: 100%;
            display: grid;
            grid-template-rows: 6in 4.8in 0.2in;
            padding: 0;
        }

        /* HERO IMAGE SECTION - PREMIUM */
        .hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 3px solid #1e40af;
        }

        .hero-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 0.3in;
            max-width: 100%;
            max-height: 100%;
        }

        .lot-badge {
            position: absolute;
            top: 0.2in;
            right: 0.2in;
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            padding: 0.12in 0.18in;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.5px;
            border-radius: 3px;
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
            text-transform: uppercase;
        }

        /* DETAILS SECTION - SOPHISTICATED */
        .details-section {
            display: grid;
            grid-template-columns: 1fr 0.9in;
            gap: 0.15in;
            padding: 0.18in;
            background: white;
        }

        .left-column {
            display: flex;
            flex-direction: column;
            gap: 0.1in;
            justify-content: space-between;
        }

        .title {
            font-size: 16px;
            font-weight: 700;
            line-height: 1.3;
            color: #0f172a;
            letter-spacing: -0.3px;
        }

        .bid-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.08in;
        }

        .bid-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 0.08in 0.1in;
            border-radius: 3px;
            border-left: 3px solid #1e40af;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .bid-label {
            font-size: 8px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
            display: block;
        }

        .bid-value {
            font-size: 12px;
            font-weight: 800;
            color: #0f172a;
            display: block;
        }

        .url-section {
            background: linear-gradient(135deg, #dbeafe 0%, #e0e7ff 100%);
            padding: 0.08in 0.1in;
            border-radius: 3px;
            border-left: 3px solid #1e40af;
        }

        .url-label {
            font-size: 7px;
            font-weight: 700;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1px;
        }

        .url-value {
            font-size: 8px;
            color: #1e40af;
            font-weight: 600;
            word-break: break-all;
            font-family: 'Courier New', monospace;
        }

        .cta {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            padding: 0.08in 0.1in;
            border-radius: 3px;
            font-size: 8px;
            font-weight: 700;
            text-align: center;
            letter-spacing: 0.3px;
        }

        .right-column {
            display: flex;
            flex-direction: column;
            gap: 0.06in;
            align-items: center;
            justify-content: flex-start;
        }

        .qr-section {
            width: 100%;
            background: white;
            padding: 0.08in;
            border-radius: 3px;
            border: 2px solid #1e40af;
            text-align: center;
            box-shadow: 0 2px 8px rgba(30, 64, 175, 0.15);
        }

        .qr-image {
            width: 100%;
            height: auto;
            max-width: 0.85in;
            display: block;
            margin: 0 auto;
        }

        .qr-label {
            font-size: 7px;
            font-weight: 700;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 3px;
        }

        .info-box {
            width: 100%;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 0.06in 0.08in;
            border-radius: 3px;
            border: 1px solid #cbd5e1;
            text-align: center;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .info-box-label {
            font-size: 7px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 2px;
            display: block;
        }

        .info-box-value {
            font-size: 11px;
            font-weight: 800;
            color: #1e40af;
            display: block;
        }

        .company-name {
            font-size: 9px;
            color: #0f172a;
            font-weight: 700;
        }

        .company-tag {
            font-size: 7px;
            color: #1e40af;
            font-weight: 700;
        }

        /* FOOTER */
        .footer {
            background: #0f172a;
            color: #94a3b8;
            text-align: center;
            font-size: 7px;
            padding: 0.04in;
            display: flex;
            align-items: center;
            justify-content: center;
            letter-spacing: 0.5px;
        }

        .print-btn {
            display: block;
            margin: 12px 0 12px 0;
            padding: 12px 24px;
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.2);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .print-btn:hover {
            background: linear-gradient(135deg, #1e3a8a 0%, #172554 100%);
        }

        @media print {
            body, html { margin: 0; padding: 0; }
            .print-btn { display: none !important; }
            .container { height: 11in; }
            * { page-break-inside: avoid !important; }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️  PRINT THIS LOT</button>

    <div class="container">
        <!-- HERO IMAGE -->
        <div class="hero">
            <div class="lot-badge">LOT {$lotNumber}</div>
            <img src="{$image}" alt="Lot {$lotNumber}" class="hero-image" onerror="this.parentElement.style.background='linear-gradient(135deg, #0f172a 0%, #1e293b 100%)';this.style.display='none';">
        </div>

        <!-- DETAILS -->
        <div class="details-section">
            <div class="left-column">
                <div class="title">{$title}</div>

                <div class="bid-grid">
                    <div class="bid-card">
                        <span class="bid-label">STARTING BID</span>
                        <span class="bid-value">{$startBid}</span>
                    </div>
                    <div class="bid-card">
                        <span class="bid-label">MIN. INCREMENT</span>
                        <span class="bid-value">{$increment}</span>
                    </div>
                    <div class="bid-card">
                        <span class="bid-label">FAIR MARKET VALUE</span>
                        <span class="bid-value">{$fmv}</span>
                    </div>
                    <div class="bid-card">
                        <span class="bid-label">EST. CLOSING</span>
                        <span class="bid-value">{$timeRemaining}</span>
                    </div>
                </div>

                <div class="url-section">
                    <span class="url-label">BID ONLINE</span>
                    <span class="url-value">{$url}</span>
                </div>

                <div class="cta">✓ REGISTER & BID INSTANTLY</div>
            </div>

            <div class="right-column">
                <div class="qr-section">
                    <img src="{$qrUrl}" alt="Bid QR Code" class="qr-image" onerror="this.style.display='none'">
                    <div class="qr-label">SCAN TO BID</div>
                </div>

                <div class="info-box">
                    <span class="info-box-label">HOUSE</span>
                    <span class="company-name">Silent</span>
                    <span class="company-tag">Bid Buddy</span>
                </div>

                <div class="info-box">
                    <span class="info-box-label">LOT #</span>
                    <span class="info-box-value">{$lotNumber}</span>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="footer">PROFESSIONAL AUCTION PLATFORM • SILENTBIDBUDDY.COM</div>
    </div>
</body>
</html>
HTML;
    }

    private function getTimeRemaining() {
        if (!isset($this->item['auction_end_time'])) {
            return '2h 14m';
        }

        try {
            $endTime = strtotime($this->item['auction_end_time']);
            $now = time();
            $diff = $endTime - $now;

            if ($diff <= 0) return 'ENDED';

            $hours = floor($diff / 3600);
            $minutes = floor(($diff % 3600) / 60);

            return ($hours > 0 ? $hours . 'h ' : '') . $minutes . 'm';
        } catch (Exception $e) {
            return '2h 14m';
        }
    }

    private function getFilename() {
        $itemId = $this->item['id'];
        $dir = __DIR__ . '/../documents/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . 'item-' . $itemId . '.html';
    }

    public function getDocumentPath() {
        return 'documents/item-' . $this->item['id'] . '.html';
    }
}
?>
