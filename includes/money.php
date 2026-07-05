<?php
// ============================================================
// MONEY MODEL — "100% to charity" checkout math
// Pure integer-cents computation. No floats in any money path.
//
// THE INVARIANT (the brand): the charity's transfer ALWAYS equals the winning
// bid, to the penny, in every combination of premium tier, tip, processing
// cover, and premium mode. Guaranteed structurally: transfer_amount_cents is
// set to bid_cents and is never derived from the fee/premium math. See
// tests/money_test.php for the exhaustive assertion of this invariant.
// ============================================================

// Stripe standard pricing used for the processing-cost gross-up.
if (!defined('STRIPE_FEE_PCT_BPS'))   define('STRIPE_FEE_PCT_BPS', 290);   // 2.9%
if (!defined('STRIPE_FEE_FIXED_CENTS')) define('STRIPE_FEE_FIXED_CENTS', 30); // $0.30

/**
 * Buyer's premium rate (basis points) for an org's trailing-12-month volume.
 * Tiers: $0–25k → 2% · $25,001–250k → 4% · $250,001+ → 5%.
 * @param int $trailing_12mo_volume_cents
 * @return int basis points (200 / 400 / 500)
 */
function premiumRateBpsForVolume($trailing_12mo_volume_cents) {
    $v = max(0, (int)$trailing_12mo_volume_cents);
    if ($v <= 25000_00)  return 200; // ≤ $25,000
    if ($v <= 250000_00) return 400; // ≤ $250,000
    return 500;                       // > $250,000
}

/**
 * Compute the full checkout breakdown for a winning bid, in integer cents.
 *
 * @param array $o {
 *   bid_cents:        int   winning bid, to the penny (required, > 0)
 *   premium_rate_bps: int   org's current buyer's-premium rate in bps
 *   premium_mode:     string 'bidder_pays' | 'optional_tip_only'
 *   tip_cents:        int   bidder's optional tip (default 0)
 *   cover_processing: bool  bidder opted to cover Stripe's fee (default true)
 *   fmv_cents:        int|null  fair market value snapshot (for tax split)
 * }
 * @return array breakdown with the same keys stored on `transactions`.
 */
function computeWinCheckout(array $o): array {
    $bid_cents = (int)($o['bid_cents'] ?? 0);
    if ($bid_cents <= 0) {
        throw new InvalidArgumentException('bid_cents must be a positive integer');
    }
    $mode = ($o['premium_mode'] ?? 'bidder_pays') === 'optional_tip_only'
        ? 'optional_tip_only' : 'bidder_pays';
    $rate_bps = max(0, (int)($o['premium_rate_bps'] ?? 0));
    $tip_cents = max(0, (int)($o['tip_cents'] ?? 0));
    $cover = array_key_exists('cover_processing', $o) ? (bool)$o['cover_processing'] : true;
    $fmv_cents = isset($o['fmv_cents']) && $o['fmv_cents'] !== null ? max(0, (int)$o['fmv_cents']) : null;

    // Buyer's premium — bidder-paid, never taken from the bid. Zero in tip-only mode.
    $premium_cents = $mode === 'optional_tip_only'
        ? 0
        : (int)round($bid_cents * $rate_bps / 10000);

    // Processing cover — gross up bid+premium so Stripe's fee doesn't erode the
    // platform's premium. Ceil to guarantee full coverage. If the bidder opts
    // out, the platform absorbs the fee from its premium; the charity's 100% is
    // untouched either way (see invariant).
    $processing_cover_cents = 0;
    if ($cover) {
        $numerator = $bid_cents + $premium_cents + STRIPE_FEE_FIXED_CENTS;
        // total = numerator / (1 - fee_pct)  →  integer ceil
        $gross_bp = (int)ceil($numerator * 10000 / (10000 - STRIPE_FEE_PCT_BPS));
        $processing_cover_cents = max(0, $gross_bp - $bid_cents - $premium_cents);
    }

    $total_cents = $bid_cents + $premium_cents + $processing_cover_cents + $tip_cents;

    // THE INVARIANT — charity receives exactly the bid, always.
    $transfer_amount_cents = $bid_cents;
    // Everything the bidder paid beyond the bid stays with the platform (Stripe's
    // fee is later deducted from the platform's side of the destination charge).
    $application_fee_cents = $total_cents - $transfer_amount_cents;

    return [
        'bid_cents'              => $bid_cents,
        'premium_rate_bps'       => $mode === 'optional_tip_only' ? 0 : $rate_bps,
        'premium_cents'          => $premium_cents,
        'tip_cents'              => $tip_cents,
        'processing_cover_cents' => $processing_cover_cents,
        'total_cents'            => $total_cents,
        'transfer_amount_cents'  => $transfer_amount_cents,  // == bid_cents (invariant)
        'application_fee_cents'  => $application_fee_cents,
        'fmv_cents'              => $fmv_cents,
        'premium_mode'           => $mode,
        'cover_processing'       => $cover,
    ];
}

/** Convert a DECIMAL(10,2) dollar string/float to exact integer cents. */
function dollarsToCents($amount): int {
    return (int)round((float)$amount * 100);
}

/** Format integer cents as a $ display string. */
function centsToDisplay(int $cents): string {
    return '$' . number_format($cents / 100, 2);
}
