<?php
// ============================================================
// RECEIPTS — split a paid win into two clearly-separated line items:
//   1) Payment to the CHARITY for the auction item (with FMV, since only the
//      amount paid above fair market value is tax-deductible).
//   2) Platform fee + tip to the platform — explicitly NOT a charitable donation.
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';
require_once __DIR__ . '/money.php';

/**
 * Build structured receipt data for a paid transaction.
 * @return array|null
 */
function buildReceiptData($transaction_id) {
    $tx = dbGetRow(
        "SELECT t.*, i.title AS item_title, i.item_number, i.fair_market_value,
                e.id AS event_id, e.name AS event_name,
                o.id AS org_id, o.name AS organization_name, o.ein
         FROM transactions t
         JOIN items i        ON i.id = t.item_id
         LEFT JOIN events e  ON e.id = i.event_id
         LEFT JOIN organizations o ON o.id = e.organization_id
         WHERE t.id = ?",
        [(int)$transaction_id]
    );
    if (!$tx) return null;

    // Prefer the authoritative cents columns; fall back to the DECIMAL amount for
    // legacy transactions that predate the money model.
    $bid_cents = $tx['transfer_amount_cents'] !== null
        ? (int)$tx['transfer_amount_cents']
        : ($tx['bid_cents'] !== null ? (int)$tx['bid_cents'] : dollarsToCents($tx['amount']));

    $premium = (int)($tx['premium_cents'] ?? 0);
    $tip     = (int)($tx['tip_cents'] ?? 0);
    $cover   = (int)($tx['processing_cover_cents'] ?? 0);
    $platform_cents = $tx['application_fee_cents'] !== null
        ? (int)$tx['application_fee_cents']
        : ($premium + $tip + $cover);

    $total_cents = $tx['total_cents'] !== null
        ? (int)$tx['total_cents']
        : ($bid_cents + $platform_cents);

    $fmv_cents = $tx['fmv_cents'] !== null
        ? (int)$tx['fmv_cents']
        : ($tx['fair_market_value'] !== null ? dollarsToCents($tx['fair_market_value']) : null);

    // Only the amount paid ABOVE fair market value is tax-deductible.
    $tax_deductible_cents = $fmv_cents !== null ? max(0, $bid_cents - $fmv_cents) : null;

    return [
        'transaction_id' => (int)$tx['id'],
        'status'         => $tx['status'],
        'paid_at'        => $tx['updated_at'],
        'total_cents'    => $total_cents,
        'charity_payment' => [
            'organization_name'    => $tx['organization_name'],
            'ein'                  => $tx['ein'],
            'event_name'           => $tx['event_name'],
            'item_title'           => $tx['item_title'],
            'item_number'          => (int)$tx['item_number'],
            'amount_cents'         => $bid_cents,
            'fmv_cents'            => $fmv_cents,
            'tax_deductible_cents' => $tax_deductible_cents,
            'note'                 => $fmv_cents !== null
                ? 'Only the amount paid above fair market value may be tax-deductible. Consult a tax advisor.'
                : 'Consult a tax advisor regarding deductibility.',
        ],
        'platform_fee' => [
            'amount_cents'           => $platform_cents,
            'premium_cents'          => $premium,
            'tip_cents'              => $tip,
            'processing_cover_cents' => $cover,
            'payee'                  => APP_NAME,
            'note'                   => 'Platform fee and optional tip. NOT a charitable donation and NOT tax-deductible.',
        ],
    ];
}
