<?php
// ============================================================
// PAYMENT REQUEST HELPERS
// Idempotent transaction handling for auction closeout + checkout
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';

/**
 * Find an existing paid transaction for a won item.
 * @param int $item_id
 * @param int $user_id
 * @return array|null
 */
function getPaidPaymentRequest($item_id, $user_id) {
    return dbGetRow(
        "SELECT *
         FROM transactions
         WHERE item_id = ? AND user_id = ? AND status = 'paid'
         ORDER BY updated_at DESC, id DESC
         LIMIT 1",
        [(int)$item_id, (int)$user_id]
    );
}

/**
 * Find the most recent pending payment request for a won item.
 * @param int $item_id
 * @param int $user_id
 * @return array|null
 */
function getPendingPaymentRequest($item_id, $user_id) {
    return dbGetRow(
        "SELECT *
         FROM transactions
         WHERE item_id = ? AND user_id = ? AND status = 'pending'
         ORDER BY updated_at DESC, id DESC
         LIMIT 1",
        [(int)$item_id, (int)$user_id]
    );
}

/**
 * Ensure one active payment request exists for the item winner.
 * Paid records are preserved; pending records are reused and refreshed.
 * @param int $item_id
 * @param int $user_id
 * @param float $amount
 * @return array
 */
function ensurePendingPaymentRequest($item_id, $user_id, $amount) {
    $paid = getPaidPaymentRequest($item_id, $user_id);
    if ($paid) {
        return [
            'success' => true,
            'created' => false,
            'already_paid' => true,
            'transaction' => $paid
        ];
    }

    $pending = getPendingPaymentRequest($item_id, $user_id);
    if ($pending) {
        dbUpdate(
            "UPDATE transactions
             SET amount = ?
             WHERE id = ?",
            [(float)$amount, (int)$pending['id']]
        );

        $pending['amount'] = (float)$amount;

        return [
            'success' => true,
            'created' => false,
            'already_paid' => false,
            'transaction' => $pending
        ];
    }

    $transaction_id = dbInsert(
        "INSERT INTO transactions (user_id, item_id, amount, status, created_at)
         VALUES (?, ?, ?, ?, NOW())",
        [(int)$user_id, (int)$item_id, (float)$amount, 'pending']
    );

    if (!$transaction_id) {
        return [
            'success' => false,
            'created' => false,
            'already_paid' => false,
            'transaction' => null
        ];
    }

    return [
        'success' => true,
        'created' => true,
        'already_paid' => false,
        'transaction' => dbGetRow(
            "SELECT * FROM transactions WHERE id = ?",
            [(int)$transaction_id]
        )
    ];
}

/**
 * Attach a new Stripe Checkout Session to an existing payment request.
 * @param int $transaction_id
 * @param string $session_id
 * @return bool
 */
function attachCheckoutSessionToPaymentRequest($transaction_id, $session_id) {
    return (bool)dbUpdate(
        "UPDATE transactions
         SET stripe_checkout_session_id = ?, status = 'pending'
         WHERE id = ? AND status != 'paid'",
        [$session_id, (int)$transaction_id]
    );
}

/**
 * Fetch a payment request by Stripe Checkout Session ID.
 * @param string $session_id
 * @return array|null
 */
function getPaymentRequestByCheckoutSession($session_id) {
    return dbGetRow(
        "SELECT *
         FROM transactions
         WHERE stripe_checkout_session_id = ?
         ORDER BY id DESC
         LIMIT 1",
        [$session_id]
    );
}

?>
