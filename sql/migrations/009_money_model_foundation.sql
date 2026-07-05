-- ============================================================
-- 009 — MONEY MODEL FOUNDATION
-- Stripe Connect + "100% to charity" money model + SaaS tiers.
-- ADDITIVE ONLY: no existing column/row is changed, so the live checkout,
-- bidding, and the imminent tester auctions keep working unchanged. The new
-- destination-charge path activates per-org once an org has a connected account.
--
-- Money policy: existing bid/item money stays DECIMAL(10,2) (exact — no floats).
-- All NEW money-model math (premium, tip, processing cover, transfer, fees) is
-- computed and stored in INTEGER CENTS so the "charity transfer == winning bid
-- to the penny" invariant is exact and provable.
-- ============================================================

-- ---- Organizations: Connect account, tax id, SaaS plan, premium config ----
ALTER TABLE organizations
    ADD COLUMN stripe_account_id           VARCHAR(255) NULL,                                   -- Connect Express acct_...
    ADD COLUMN stripe_charges_enabled      TINYINT(1)   NOT NULL DEFAULT 0,
    ADD COLUMN stripe_payouts_enabled      TINYINT(1)   NOT NULL DEFAULT 0,
    ADD COLUMN stripe_onboarding_status    ENUM('none','pending','complete','restricted') NOT NULL DEFAULT 'none',
    ADD COLUMN ein                         VARCHAR(20)  NULL,                                   -- charity EIN for receipts
    ADD COLUMN plan                        ENUM('free','pro','enterprise') NOT NULL DEFAULT 'free',
    ADD COLUMN premium_mode                ENUM('bidder_pays','optional_tip_only') NOT NULL DEFAULT 'bidder_pays',
    ADD COLUMN premium_rate_bps            INT UNSIGNED NOT NULL DEFAULT 200,                   -- basis points (200 = 2.00%)
    ADD COLUMN trailing_12mo_volume_cents  BIGINT UNSIGNED NOT NULL DEFAULT 0,                  -- drives the tier; recomputed nightly
    ADD COLUMN premium_tier_computed_at    DATETIME NULL,
    ADD COLUMN stripe_customer_id          VARCHAR(255) NULL,                                   -- SaaS billing customer
    ADD COLUMN stripe_subscription_id      VARCHAR(255) NULL,
    ADD COLUMN subscription_status         VARCHAR(40)  NULL;

ALTER TABLE organizations
    ADD KEY idx_org_stripe_account (stripe_account_id),
    ADD KEY idx_org_plan (plan);

-- ---- Transactions: full money breakdown in integer cents ----
-- transactions.amount (DECIMAL) is retained for backward compatibility; the cents
-- columns below are the authoritative money-model record for a paid win.
ALTER TABLE transactions
    ADD COLUMN organization_id        INT UNSIGNED NULL,
    ADD COLUMN connected_account_id   VARCHAR(255) NULL,          -- destination acct at charge time
    ADD COLUMN bid_cents              INT UNSIGNED NULL,          -- the winning bid, to the penny
    ADD COLUMN premium_cents          INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN premium_rate_bps       INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN tip_cents              INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN processing_cover_cents INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN total_cents            INT UNSIGNED NULL,          -- total charged to the bidder
    ADD COLUMN transfer_amount_cents  INT UNSIGNED NULL,          -- transferred to the charity (MUST == bid_cents)
    ADD COLUMN application_fee_cents  INT UNSIGNED NULL,          -- retained by the platform
    ADD COLUMN fmv_cents              INT UNSIGNED NULL,          -- fair market value snapshot (tax: only amount above FMV is deductible)
    ADD COLUMN idempotency_key        VARCHAR(80)  NULL;

ALTER TABLE transactions
    ADD UNIQUE KEY uq_tx_idempotency (idempotency_key),
    ADD KEY idx_tx_org (organization_id);

-- ---- Ledger: immutable, append-only audit trail of every money movement ----
-- One row per money leg. The per-event "100% guarantee" report reconciles:
--   SUM(amount_cents WHERE entry_type='bid_to_charity') == SUM(winning bids).
CREATE TABLE IF NOT EXISTS ledger (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    transaction_id   INT UNSIGNED NULL,
    organization_id  INT UNSIGNED NULL,
    event_id         INT UNSIGNED NULL,
    item_id          INT UNSIGNED NULL,
    entry_type       ENUM('bid_to_charity','buyers_premium','tip','processing_cover',
                          'refund_charity','refund_platform','payout') NOT NULL,
    recipient        ENUM('charity','platform') NOT NULL,
    amount_cents     BIGINT NOT NULL,                              -- signed; negative on refunds
    stripe_object_id VARCHAR(255) NULL,                            -- pi_/tr_/re_/fee_ id
    memo             VARCHAR(255) NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ledger_event (event_id),
    KEY idx_ledger_org (organization_id),
    KEY idx_ledger_tx (transaction_id),
    KEY idx_ledger_type (entry_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
