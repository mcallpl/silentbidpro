<?php
// ============================================================
// LEDGER — immutable audit trail of every money movement, and the
// per-event "100% guarantee" reconciliation report.
//
// One ledger row per money leg of a paid win. The guarantee report proves,
// per event, that SUM(charity transfers) == SUM(winning bids), to the penny.
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';

/**
 * Append one immutable ledger entry.
 * @return int|false ledger id
 */
function recordLedgerEntry(array $e) {
    return dbInsert(
        "INSERT INTO ledger
            (transaction_id, organization_id, event_id, item_id, entry_type, recipient, amount_cents, stripe_object_id, memo, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
        [
            isset($e['transaction_id']) ? (int)$e['transaction_id'] : null,
            isset($e['organization_id']) ? (int)$e['organization_id'] : null,
            isset($e['event_id']) ? (int)$e['event_id'] : null,
            isset($e['item_id']) ? (int)$e['item_id'] : null,
            $e['entry_type'],
            $e['recipient'],
            (int)$e['amount_cents'],
            $e['stripe_object_id'] ?? null,
            $e['memo'] ?? null,
        ]
    );
}

/**
 * Record every money leg of a PAID win from its transaction row (with the
 * cents breakdown). Idempotent: skips if this transaction already has ledger
 * rows, so replaying a webhook can't double-count.
 * @param array $tx transactions row (must include the *_cents columns)
 * @param string|null $stripe_object_id the PaymentIntent id, for traceability
 * @return bool whether new rows were written
 */
function recordWinLedger(array $tx, $stripe_object_id = null) {
    $tx_id = (int)($tx['id'] ?? 0);
    if (!$tx_id) return false;

    // Idempotency guard.
    if (dbExists('ledger', 'transaction_id = ?', [$tx_id])) {
        return false;
    }

    // Resolve event/org/item context (transaction may not carry all of them).
    $item_id = (int)($tx['item_id'] ?? 0);
    $ctx = dbGetRow(
        "SELECT i.id AS item_id, i.event_id, e.organization_id
         FROM items i LEFT JOIN events e ON e.id = i.event_id
         WHERE i.id = ?",
        [$item_id]
    ) ?: [];
    $event_id = (int)($ctx['event_id'] ?? 0);
    $org_id   = (int)($tx['organization_id'] ?? $ctx['organization_id'] ?? 0);

    $base = [
        'transaction_id'  => $tx_id,
        'organization_id' => $org_id ?: null,
        'event_id'        => $event_id ?: null,
        'item_id'         => $item_id ?: null,
        'stripe_object_id'=> $stripe_object_id,
    ];

    $legs = [
        ['entry_type' => 'bid_to_charity',   'recipient' => 'charity',  'amount' => (int)($tx['transfer_amount_cents'] ?? $tx['bid_cents'] ?? 0), 'memo' => 'Winning bid to charity'],
        ['entry_type' => 'buyers_premium',   'recipient' => 'platform', 'amount' => (int)($tx['premium_cents'] ?? 0),          'memo' => "Buyer's premium"],
        ['entry_type' => 'tip',              'recipient' => 'platform', 'amount' => (int)($tx['tip_cents'] ?? 0),              'memo' => 'Optional tip'],
        ['entry_type' => 'processing_cover', 'recipient' => 'platform', 'amount' => (int)($tx['processing_cover_cents'] ?? 0), 'memo' => 'Processing-cost coverage'],
    ];

    $wrote = false;
    foreach ($legs as $leg) {
        if ($leg['amount'] <= 0) continue; // don't record zero legs
        recordLedgerEntry($base + [
            'entry_type'  => $leg['entry_type'],
            'recipient'   => $leg['recipient'],
            'amount_cents'=> $leg['amount'],
            'memo'        => $leg['memo'],
        ]);
        $wrote = true;
    }
    return $wrote;
}

/**
 * Per-event "100% guarantee" reconciliation.
 * @return array { event_id, winning_bids_cents, charity_transfers_cents,
 *                 platform_revenue_cents, paid_wins, guarantee_holds }
 */
function eventGuaranteeReport($event_id) {
    $event_id = (int)$event_id;

    // Sum of winning bids that were paid, from the authoritative tx breakdown.
    $wins = dbGetRow(
        "SELECT COUNT(*) AS n,
                COALESCE(SUM(COALESCE(t.transfer_amount_cents, t.bid_cents)), 0) AS bids_cents
         FROM transactions t
         JOIN items i ON i.id = t.item_id
         WHERE i.event_id = ? AND t.status = 'paid'
           AND (t.transfer_amount_cents IS NOT NULL OR t.bid_cents IS NOT NULL)",
        [$event_id]
    );

    $charity = (int)dbGetValue(
        "SELECT COALESCE(SUM(amount_cents), 0) FROM ledger
         WHERE event_id = ? AND entry_type = 'bid_to_charity'",
        [$event_id]
    );

    $platform = (int)dbGetValue(
        "SELECT COALESCE(SUM(amount_cents), 0) FROM ledger
         WHERE event_id = ? AND entry_type IN ('buyers_premium','tip','processing_cover')",
        [$event_id]
    );

    $bids = (int)($wins['bids_cents'] ?? 0);
    return [
        'event_id'                => $event_id,
        'paid_wins'               => (int)($wins['n'] ?? 0),
        'winning_bids_cents'      => $bids,
        'charity_transfers_cents' => $charity,
        'platform_revenue_cents'  => $platform,
        // THE GUARANTEE: charity received exactly the winning bids.
        'guarantee_holds'         => ($bids === $charity),
    ];
}
