# PROMPT FOR CLAUDE CODE — Silent Bid Pro iOS Parity & App Store Submission

Copy everything below this line into Claude Code.

---

## ROLE

You are the lead engineering team of a top-tier, $100M IT consulting firm engaged for a fixed-scope, high-stakes deliverable. Your client is the owner of **Silent Bid Pro**, a silent-auction web application. You operate with the rigor of a firm whose reputation depends on this shipping: you audit before you build, you document every decision, you never guess when you can verify, and you treat App Store rejection as a project failure. No shortcuts, no placeholder code, no "TODO" left behind.

## ENGAGEMENT OBJECTIVE

Bring the **Silent Bid Pro iOS (iPhone) app** to complete, flawless feature parity with the production web app, and prepare it for Apple App Store submission with the highest possible probability of first-pass approval.

## PHASE 0 — DISCOVERY & AUDIT (do this first, before writing any code)

1. Locate and fully read the web app codebase in this repository/workspace. Identify the stack, every route, every user-facing feature, every API endpoint, every user role (bidder, auction organizer, admin), and every business rule (bid increments, anti-sniping/auto-extend, reserve prices, buy-now, notifications, payouts, receipts — whatever exists).
2. Locate and fully read the existing iOS app codebase. Identify its stack (SwiftUI, UIKit, React Native, Flutter, etc.) and its current state.
3. Produce a **Feature Parity Matrix** (`PARITY_MATRIX.md`): every web feature listed in rows, with columns for iOS status (Complete / Partial / Missing / Broken), notes, and priority. Do not proceed until this matrix is written and saved.
4. Produce a **Remediation Plan** (`REMEDIATION_PLAN.md`) ordering all work into milestones. Present both documents to me for a checkpoint before Phase 1. Ask me any blocking questions now, not mid-build.

## PHASE 1 — FEATURE PARITY IMPLEMENTATION

Work milestone by milestone from the Remediation Plan. Rules of engagement:

- Match web behavior exactly unless iOS platform conventions demand otherwise; where they differ, follow Apple's Human Interface Guidelines and document the deviation.
- Real-time bidding must be flawless: live bid updates, correct handling of race conditions on simultaneous bids, graceful reconnection on network loss, and server-authoritative bid validation (never trust the client for bid amounts or timing).
- All features must work end-to-end against the same backend/API as the web app. If API changes are needed, implement them in a backward-compatible way and document them.
- Handle every state: loading, empty, error, offline, expired auction, outbid, won, payment pending, payment failed.

## PAYMENTS — CRITICAL, READ CAREFULLY (App Store Guideline 3.1)

This is the #1 rejection risk for auction apps. Implement it exactly as follows:

- Silent auction items are **physical goods and services consumed outside the app**. Under App Store Guideline **3.1.3(e)/3.1.5(a)**, these purchases **MUST use payment methods other than In-App Purchase**. Do **NOT** implement StoreKit/IAP for auction item payments — that itself causes rejection and forfeits 30% to Apple unnecessarily.
- Implement **Apple Pay as the primary, first-class payment method**, using PassKit (`PKPaymentRequest`) with **Stripe as the payment processor behind it** (Stripe's iOS SDK supports Apple Pay natively via `STPApplePayContext` / PaymentSheet). Apple Pay on iOS is a wallet/UX layer; Stripe remains the processor. This is the standard, Apple-approved architecture.
- Also support card entry via Stripe PaymentSheet as a fallback for users without Apple Pay configured.
- Register and validate the Apple Pay **merchant identifier**, configure the payment-processing certificate with Stripe, and test in the sandbox and on a physical device.
- **Exception check:** if the app sells ANY digital content or app functionality (e.g., a paid "Pro" subscription for auction organizers), that specific item MUST use In-App Purchase/StoreKit. Audit for this and flag it to me if found.
- All prices, currency handling, tax/fee disclosure, and receipts must match the web app.

## PHASE 2 — APP STORE READINESS (Apple review is tedious; leave nothing to chance)

Implement and verify every item; produce `APP_STORE_CHECKLIST.md` with each item checked off with evidence:

1. **Account requirements:** If the app supports account creation, it must support **in-app account deletion** (Guideline 5.1.1(v)). If any third-party login (Google, Facebook) is offered, **Sign in with Apple must also be offered** (Guideline 4.8).
2. **Privacy:** Complete `PrivacyInfo.xcprivacy` privacy manifest, App Privacy "nutrition label" data mapping, App Tracking Transparency prompt ONLY if tracking occurs, purpose strings (`NSCameraUsageDescription`, etc.) for every permission actually used — and remove any permission not used.
3. **Content & legal:** Terms of Service, Privacy Policy links reachable in-app; auction rules disclosed; no misleading claims.
4. **HIG compliance:** native navigation patterns, Dynamic Type, Dark Mode, safe areas on all current iPhone models, no clipped or broken layouts.
5. **Accessibility:** VoiceOver labels on all interactive elements, sufficient contrast, minimum 44pt touch targets.
6. **Push notifications:** permission requested in context (not at launch), functional outbid/auction-ending alerts, no notification spam.
7. **Performance & stability:** zero crashes, no memory leaks, cold launch under 2 seconds on the oldest supported device, all network failures handled gracefully.
8. **Reviewer experience:** create and document a **demo account with pre-seeded live auction data** so the Apple reviewer can experience bidding end-to-end without real payment; include this in App Review notes along with an explanation that payments are for physical goods via Apple Pay/Stripe per Guideline 3.1.3(e).
9. **Metadata package:** App name, subtitle, keywords, description, screenshots for required device sizes, app icon in all required resolutions, age rating questionnaire answers, support URL, marketing URL.

## PHASE 3 — QUALITY ASSURANCE

- Write unit tests for all bidding logic, payment flows (mocked), and API layers; UI tests for the critical paths (browse → bid → outbid → rebid → win → pay).
- Run the full test suite and fix every failure. Run static analysis / linting and resolve all warnings.
- Produce a manual **Device Test Plan** (`QA_TEST_PLAN.md`) covering iPhone SE through the current Pro Max, iOS versions from your chosen minimum target to latest, portrait orientation, poor-network conditions, and interrupted-payment scenarios.

## OPERATING PRINCIPLES

- Checkpoint with me at the end of each phase with a concise status report before proceeding.
- If you find something ambiguous, security-relevant (e.g., client-side bid validation, exposed keys, missing auth on endpoints), or a discrepancy between web and iOS business logic — **stop and flag it**, do not silently pick an interpretation.
- Never commit secrets. Verify all API keys are environment-configured.
- Final deliverable: a build that archives cleanly in Xcode, passes App Store Connect validation, and a completed `APP_STORE_CHECKLIST.md` I can follow to submit.

Begin with Phase 0 now.
