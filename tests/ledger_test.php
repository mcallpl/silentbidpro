<?php
// ============================================================
// LEDGER + RECEIPT TESTS
// Seeds a paid win in a rolled-back DB transaction, records the ledger,
// runs the per-event guarantee report, and builds the split receipt.
// Run: php tests/ledger_test.php
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db-helpers.php';
require_once __DIR__ . '/../includes/money.php';
require_once __DIR__ . '/../includes/ledger.php';
require_once __DIR__ . '/../includes/receipts.php';

$pass = 0; $fail = 0; $failures = [];
function check($c, $m) { global $pass,$fail,$failures; if ($c) $pass++; else { $fail++; $failures[]=$m; } }

$db = getDB();
$db->begin_transaction();

// --- Seed a scratch org / event / item ---
$org_id = dbInsert("INSERT INTO organizations (name, slug, ein, plan, premium_mode, premium_rate_bps, created_at)
                    VALUES (?,?,?,?,?,?,NOW())",
                   ['Test Charity', 'test-charity-'.substr(md5('x'),0,6), '12-3456789', 'free', 'bidder_pays', 400]);
$event_id = dbInsert("INSERT INTO events (organization_id, name, slug, status, auction_end_time, created_at)
                      VALUES (?,?,?, 'open', DATE_ADD(NOW(), INTERVAL 1 DAY), NOW())",
                     [$org_id, 'Test Gala', 'test-gala-'.substr(md5('y'),0,6)]);
$user_id = dbInsert("INSERT INTO users (phone_number, full_name, created_at) VALUES (?,?,NOW())",
                    ['+15550007777','Ledger Tester']);

// Two winning items so the report sums more than one.
$wins = [
    ['fmv' => 300.00, 'bid' => 500.00, 'tip' => 20.00, 'cover' => true],
    ['fmv' => 120.00, 'bid' => 155.55, 'tip' => 0.00,  'cover' => false],
];
$expected_charity_cents = 0;
$tx_ids = [];
foreach ($wins as $w) {
    $item_id = dbInsert("INSERT INTO items (event_id, item_number, title, starting_bid, min_increment, current_high_bid, current_high_bidder_id, fair_market_value, auction_end_time, is_closed, created_at)
                         VALUES (?,?,?,?,?,?,?,?, DATE_SUB(NOW(), INTERVAL 1 HOUR), 1, NOW())",
                        [$event_id, rand(9000,9999), 'Win Item', $w['bid'], 5.00, $w['bid'], $user_id, $w['fmv']]);

    $b = computeWinCheckout([
        'bid_cents' => dollarsToCents($w['bid']),
        'premium_rate_bps' => 400,
        'premium_mode' => 'bidder_pays',
        'tip_cents' => dollarsToCents($w['tip']),
        'cover_processing' => $w['cover'],
        'fmv_cents' => dollarsToCents($w['fmv']),
    ]);
    $expected_charity_cents += $b['bid_cents'];

    $tx_id = dbInsert(
        "INSERT INTO transactions
            (user_id, item_id, organization_id, amount, status,
             bid_cents, premium_cents, premium_rate_bps, tip_cents, processing_cover_cents,
             total_cents, transfer_amount_cents, application_fee_cents, fmv_cents, created_at)
         VALUES (?,?,?,?, 'paid', ?,?,?,?,?,?,?,?,?, NOW())",
        [$user_id, $item_id, $org_id, $b['bid_cents']/100,
         $b['bid_cents'], $b['premium_cents'], $b['premium_rate_bps'], $b['tip_cents'], $b['processing_cover_cents'],
         $b['total_cents'], $b['transfer_amount_cents'], $b['application_fee_cents'], $b['fmv_cents']]
    );
    $tx_ids[] = $tx_id;

    $tx = dbGetRow("SELECT * FROM transactions WHERE id = ?", [$tx_id]);
    check(recordWinLedger($tx, 'pi_test_'.$tx_id) === true, "ledger written for tx $tx_id");
    // Idempotency: a second call writes nothing.
    check(recordWinLedger($tx, 'pi_test_'.$tx_id) === false, "ledger idempotent for tx $tx_id");
}

// --- Guarantee report ---
$rep = eventGuaranteeReport($event_id);
check($rep['paid_wins'] === 2, "report counts 2 paid wins (got {$rep['paid_wins']})");
check($rep['winning_bids_cents'] === $expected_charity_cents, "winning bids = expected ($expected_charity_cents)");
check($rep['charity_transfers_cents'] === $expected_charity_cents, "charity transfers = winning bids");
check($rep['guarantee_holds'] === true, "100% GUARANTEE HOLDS (bids==transfers)");
check($rep['platform_revenue_cents'] > 0, "platform earned premium/tip/cover");

// --- Split receipt for the first win ($500 bid, $300 FMV, $20 tip, cover on) ---
$r = buildReceiptData($tx_ids[0]);
check($r['charity_payment']['amount_cents'] === 500_00, "receipt: charity paid \$500.00");
check($r['charity_payment']['fmv_cents'] === 300_00, "receipt: FMV \$300.00");
check($r['charity_payment']['tax_deductible_cents'] === 200_00, "receipt: deductible = bid-FMV = \$200.00");
check($r['charity_payment']['ein'] === '12-3456789', "receipt: charity EIN present");
check($r['platform_fee']['amount_cents'] === $r['platform_fee']['premium_cents'] + $r['platform_fee']['tip_cents'] + $r['platform_fee']['processing_cover_cents'], "receipt: platform fee = premium+tip+cover");
check($r['total_cents'] === $r['charity_payment']['amount_cents'] + $r['platform_fee']['amount_cents'], "receipt: total = charity + platform");

$db->rollback();

echo "PASS: $pass   FAIL: $fail\n";
if ($fail) { echo "\nFAILURES:\n - ".implode("\n - ", $failures)."\n"; exit(1); }
echo "✅ Ledger reconciles, 100% guarantee holds, receipt splits correctly.\n";
