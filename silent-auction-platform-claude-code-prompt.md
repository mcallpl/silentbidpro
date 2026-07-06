# CLAUDE CODE BUILD PROMPT — "100% to Charity" Silent Auction Platform

Copy everything below this line into Claude Code.

---

## PROJECT OVERVIEW

Build a silent auction fundraising platform for nonprofits consisting of:

1. **A full-featured web application** (primary product — all payments happen here)
2. **A native iOS app** (companion experience — browsing, bidding, notifications; all checkout is routed to the web via external payment, keeping us fully compliant with Apple's guidelines and keeping Apple's commission at 0%)

**The non-negotiable business rule:** 100% of every winning bid amount goes to the nonprofit. The platform earns money exclusively from (a) a buyer's premium and/or optional tip paid by the bidder ON TOP of the bid, and (b) SaaS subscription tiers sold on the web. The nonprofit's proceeds are never touched.

**The scaling rule:** Platform revenue must scale with organization size. Small nonprofits pay almost nothing; large organizations generate proportionally more revenue for the platform via a tiered buyer's premium and paid SaaS tiers.

---

## TECH STACK

- **Web:** Next.js (App Router) + TypeScript + Tailwind, deployed on Vercel
- **Backend:** Next.js API routes / server actions + PostgreSQL (Supabase or Neon) + Prisma
- **Payments:** Stripe Connect (Express accounts for nonprofits) — this is the heart of the system, details below
- **Auth:** Supabase Auth or NextAuth (email magic link + Google/Apple sign-in)
- **iOS:** Swift/SwiftUI native app consuming the same REST/tRPC API
- **Realtime bidding:** Supabase Realtime or Pusher for live bid updates
- **Email/receipts:** Resend or SendGrid
- **Infra rule:** every dollar amount stored as integer cents. No floats. Ever.

---

## PAYMENT ARCHITECTURE (STRIPE CONNECT) — READ CAREFULLY, THIS IS THE CORE

### Account model
- Each nonprofit onboards via **Stripe Connect Express**. They complete Stripe-hosted KYC/onboarding and connect their own bank account. We never hold their money and never take custody of donations (this dramatically reduces our regulatory burden).
- The platform has its own Stripe account that collects application fees.

### Charge flow for a winning bid (destination charge pattern)
When a bidder pays for a won item, construct a single Stripe PaymentIntent as follows:

```
winning_bid            = e.g. $500.00  (50000 cents)
buyers_premium         = winning_bid × premium_rate (tiered, see below)
processing_cover       = gross-up so that Stripe fees never touch the bid
                         (solve: total = (winning_bid + buyers_premium + 0.30) / (1 - 0.029))
optional_tip           = whatever the bidder adds (default suggestions: 0%, 3%, 5%, 8%)

total_charged_to_bidder = winning_bid + buyers_premium + processing_cover + optional_tip
```

Create the PaymentIntent **on the platform account** with:
- `amount = total_charged_to_bidder`
- `transfer_data[destination] = nonprofit's connected account`
- `transfer_data[amount] = winning_bid` ← **exactly the bid, to the penny**
- Everything else (premium + processing cover + tip) remains with the platform; the platform pays Stripe's processing fee out of the processing_cover it collected.

This guarantees, structurally and provably, that the nonprofit receives 100.00% of the winning bid. Build an automated "100% guarantee" ledger report per event showing: sum of winning bids == sum of transfers to the nonprofit.

### Tiered buyer's premium (how the platform scales with org size)
The premium is paid by the BIDDER, not the charity, and it tiers on the organization's **trailing-12-month gross auction volume on the platform**:

| Org's trailing 12-mo volume | Buyer's premium |
|---|---|
| $0 – $25,000 | 2% |
| $25,001 – $250,000 | 4% |
| $250,001+ | 5% |

- Recompute the org's tier nightly via a cron job.
- Display the premium transparently at checkout: "Your winning bid of $500 goes 100% to [Charity]. A 2% platform fee ($10.00) supports the free tools that make this auction possible."
- Add an org-level setting: `premium_mode = "bidder_pays" | "optional_tip_only"`. In tip-only mode (available to the Free tier's smallest orgs if we choose), premium is $0 and revenue comes only from tips.
- Optional tip UI mirrors GoFundMe/Givebutter: preselected suggestion with easy adjustment to $0. Never dark-pattern it.

### Processing fee coverage
- Checkout includes a pre-checked (but clearly labeled and easily uncheckable) "Cover processing costs so [Charity] receives every penny" line.
- If the bidder unchecks it, the platform absorbs the Stripe fee from its premium — **the nonprofit's 100% is protected in every code path.** Write unit tests asserting this invariant.

### Receipts & tax
- Auto-generate two line items on the receipt: (1) payment to [Charity] for auction item — with FMV field the charity can set, since only the amount above fair market value is tax-deductible; (2) platform fee/tip to [Platform, Inc.] — explicitly NOT a charitable donation.
- Store the charity's EIN and include it on receipts.

---

## SAAS SUBSCRIPTION TIERS (second revenue engine — WEB ONLY)

Sold exclusively on the website via Stripe Billing. **Never surface pricing, upgrade buttons, or purchase links inside the iOS app** (Slack/Zoom model — fully compliant, Apple takes nothing).

- **Free — "Seedling":** unlimited items, 1 active event at a time, standard branding, email support. This tier must be genuinely great; it's our word-of-mouth engine with small nonprofits.
- **Pro — $99/mo:** custom branding, 3 concurrent events, live-event big-screen display mode, CSV exports, priority support.
- **Enterprise — $399/mo:** unlimited events, multi-chapter management, Salesforce/Blackbaud CRM integrations, dedicated success manager, API access, SSO.

Gate features server-side by `org.plan`. iOS app reads the plan and adjusts features but never mentions payment.

---

## iOS APP — APPLE COMPLIANCE ARCHITECTURE (CRITICAL)

Design the iOS app so Apple's IAP requirement never applies:

1. **Physical goods rule:** silent auction items are physical goods/experiences. Per App Store Review Guideline 3.1.3(e), payment for physical goods must use methods other than IAP. All auction checkout uses our Stripe web checkout.
2. **Checkout handoff:** when a bidder wins, the iOS app shows "Complete your payment" and opens our web checkout in `SFSafariViewController` (or ASWebAuthenticationSession for a logged-in handoff token). Do NOT embed Stripe payment sheets natively in v1 — the web handoff is the cleanest review-proof path. (Native Stripe SDK for physical goods is technically allowed, but web handoff removes all review ambiguity; we can revisit later.)
3. **No digital purchases in-app:** no subscription upsells, no "upgrade" screens, no pricing pages, no links to pricing. Organizer-side plan management is web-only. The iOS app is for bidders and for organizers to monitor events.
4. **Donations:** if we add a pure "donate" button later, route it the same way (web checkout); Apple permits external payment for donations to registered 501(c)(3)s.
5. App Review notes template: include a written explanation in App Store Connect review notes stating the app facilitates bidding on physical auction items for registered nonprofits, with payment via web per guideline 3.1.3(e).

iOS feature set: browse events/items, place & auto-max bids, push notifications ("You've been outbid!" — this is the killer engagement feature), watch lists, win notifications with checkout handoff, organizer dashboard (read-only stats).

---

## CORE DOMAIN MODEL

```
Organization (nonprofit) — name, EIN, stripe_account_id, plan, premium_mode, trailing_12mo_volume, current_premium_rate
User — bidder or organizer (role per org membership)
Event — belongs to Organization; start/end times, timezone, status
Item — belongs to Event; title, photos, FMV, starting_bid, min_increment, buy_now_price?
Bid — item_id, user_id, amount_cents, max_proxy_amount_cents?, created_at
Win — item_id, user_id, winning_amount_cents, payment_status
Payment — win_id, stripe_payment_intent_id, bid_cents, premium_cents, processing_cover_cents, tip_cents, total_cents, transfer_amount_cents (must == bid_cents)
LedgerEntry — immutable audit trail of every money movement
```

Bidding rules to implement: minimum increments, proxy (max) bidding, anti-sniping soft-close (extend item close by 2 min if a bid lands in the final 2 min), tie-breaking by timestamp, organizer ability to void a bid with audit log.

---

## BUILD ORDER

1. Schema + Stripe Connect Express onboarding flow for orgs
2. Event & item CRUD with image upload
3. Bidding engine with realtime updates + proxy bidding + soft close
4. Checkout: PaymentIntent construction exactly per the payment architecture above, with unit tests asserting `transfer_amount == winning_bid` in every scenario (tip on/off, processing cover on/off, every premium tier)
5. Receipts, email notifications, organizer payout dashboard ("You received 100.00% of $X raised")
6. SaaS billing (Stripe Billing) + feature gating
7. iOS app (SwiftUI) consuming the API with web checkout handoff
8. Admin/ops dashboard: tier recomputation job, ledger reconciliation report, "100% guarantee" report per event

## NON-FUNCTIONAL REQUIREMENTS

- Idempotency keys on all payment mutations; Stripe webhooks (payment_intent.succeeded, account.updated, charge.refunded) drive state, never client callbacks alone
- Refund path: refunding a win reverses the transfer and the platform fee proportionally
- WCAG AA accessibility; gala attendees skew older — large type, high contrast
- Load target: 500 concurrent bidders per event without bid-ordering errors (use DB-level serialization or advisory locks on item bid writes)
- Every screen that shows money must show the "100% to charity" breakdown — it's the brand

---

## WHAT SUCCESS LOOKS LIKE

A $1,500 church auction runs free, the charity receives $1,500.00 to the penny, and the platform earns ~$30 in premiums. A $2M hospital gala runs on Enterprise, the hospital receives $2,000,000.00 to the penny, and the platform earns ~$100,000 in premiums plus $399/mo. Same code path, same guarantee, revenue that scales with impact.
