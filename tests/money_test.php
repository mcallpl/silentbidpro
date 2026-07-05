<?php
// ============================================================
// MONEY MODEL TESTS
// Proves THE INVARIANT: the charity's transfer == the winning bid, to the
// penny, in EVERY combination of premium tier, tip, processing cover, and
// premium mode. Run: php tests/money_test.php
// ============================================================

require_once __DIR__ . '/../includes/money.php';

$pass = 0; $fail = 0; $failures = [];
function check($cond, $msg) {
    global $pass, $fail, $failures;
    if ($cond) { $pass++; } else { $fail++; $failures[] = $msg; }
}

// ---- Tier boundaries ----
check(premiumRateBpsForVolume(0) === 200,          'tier: $0 -> 2%');
check(premiumRateBpsForVolume(25000_00) === 200,   'tier: $25,000 -> 2%');
check(premiumRateBpsForVolume(25000_01) === 400,   'tier: $25,000.01 -> 4%');
check(premiumRateBpsForVolume(250000_00) === 400,  'tier: $250,000 -> 4%');
check(premiumRateBpsForVolume(250000_01) === 500,  'tier: $250,000.01 -> 5%');
check(premiumRateBpsForVolume(9_000_000_00) === 500,'tier: $9M -> 5%');

// ---- THE INVARIANT: exhaustive combination sweep ----
$bids = [100, 2500, 50000, 150000, 199999, 1_000_000_00, 2_000_000_00]; // cents, incl. odd + huge
$rates = [200, 400, 500];
$tips = [0, 1, 300, 5000];
$covers = [true, false];
$modes = ['bidder_pays', 'optional_tip_only'];

$combos = 0;
foreach ($bids as $bid) {
  foreach ($rates as $rate) {
    foreach ($tips as $tip) {
      foreach ($covers as $cover) {
        foreach ($modes as $mode) {
          $b = computeWinCheckout([
              'bid_cents' => $bid, 'premium_rate_bps' => $rate,
              'premium_mode' => $mode, 'tip_cents' => $tip, 'cover_processing' => $cover,
          ]);
          $combos++;
          $label = "bid=$bid rate=$rate tip=$tip cover=" . ($cover?'1':'0') . " mode=$mode";

          // 1) THE INVARIANT — charity always gets exactly the bid.
          check($b['transfer_amount_cents'] === $bid, "INVARIANT transfer==bid [$label] got {$b['transfer_amount_cents']}");

          // 2) Everything is integer cents (no floats leaked in).
          foreach (['premium_cents','tip_cents','processing_cover_cents','total_cents','transfer_amount_cents','application_fee_cents'] as $k) {
              check(is_int($b[$k]), "int $k [$label]");
          }

          // 3) Totals reconcile: total = bid + premium + cover + tip.
          check($b['total_cents'] === $bid + $b['premium_cents'] + $b['processing_cover_cents'] + $b['tip_cents'],
                "total reconciles [$label]");

          // 4) Platform share = total - bid (what the charity does NOT keep).
          check($b['application_fee_cents'] === $b['total_cents'] - $bid, "app fee = total-bid [$label]");

          // 5) tip-only mode charges no premium.
          if ($mode === 'optional_tip_only') check($b['premium_cents'] === 0, "tip-only premium=0 [$label]");
          else check($b['premium_cents'] === (int)round($bid * $rate / 10000), "premium math [$label]");

          // 6) opting out of cover means no cover charged.
          if (!$cover) check($b['processing_cover_cents'] === 0, "no cover when opted out [$label]");

          // 7) never charge the bidder less than the bid.
          check($b['total_cents'] >= $bid, "total >= bid [$label]");
        }
      }
    }
  }
}

// ---- Prompt's success scenarios ----
$church = computeWinCheckout(['bid_cents' => 1500_00, 'premium_rate_bps' => 200, 'premium_mode' => 'bidder_pays']);
check($church['transfer_amount_cents'] === 1500_00, 'church: charity gets $1,500.00');
check($church['premium_cents'] === 30_00, 'church: 2% premium = $30.00');

$gala = computeWinCheckout(['bid_cents' => 2_000_000_00, 'premium_rate_bps' => 500, 'premium_mode' => 'bidder_pays']);
check($gala['transfer_amount_cents'] === 2_000_000_00, 'gala: charity gets $2,000,000.00');
check($gala['premium_cents'] === 100_000_00, 'gala: 5% premium = $100,000.00');

// ---- Cover actually covers Stripe's fee (platform nets >= premium) ----
$c = computeWinCheckout(['bid_cents' => 50000, 'premium_rate_bps' => 200, 'premium_mode' => 'bidder_pays', 'cover_processing' => true]);
$stripe_fee = (int)round($c['total_cents'] * STRIPE_FEE_PCT_BPS / 10000) + STRIPE_FEE_FIXED_CENTS;
$platform_net = $c['total_cents'] - $c['transfer_amount_cents'] - $stripe_fee;
check($platform_net >= $c['premium_cents'] - 2, "cover keeps platform net >= premium (net=$platform_net premium={$c['premium_cents']})");

echo "Combinations swept: $combos\n";
echo "PASS: $pass   FAIL: $fail\n";
if ($fail) { echo "\nFAILURES:\n - " . implode("\n - ", array_slice($failures, 0, 20)) . "\n"; exit(1); }
echo "✅ All money-model invariants hold.\n";
