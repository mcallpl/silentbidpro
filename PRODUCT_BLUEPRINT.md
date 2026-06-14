# Silent Bid Buddy Product Blueprint

## Product Direction

Silent Bid Buddy is a reusable hybrid silent auction platform for nonprofits. It should feel warm, cheerful, donor-trustworthy, clean, and operational. The product should support in-person gala bidding through QR codes, online bidding before or during the event, and admin closeout workflows that help staff and volunteers finish the night calmly.

Givebutter is the primary benchmark for feature coverage and donor ease. Silent Bid Buddy should match the important auction loops, then improve on auction-night operations: faster QR bidding, clearer admin closeout, better printed materials, pickup tracking, and less operational clutter.

## Core Positioning

Silent Bid Buddy helps nonprofits run a hybrid silent auction where bidders can scan, browse, bid, get reminders, pay, and pick up items without staff chasing them manually.

The product should eventually support many organizations and many auction events, but the first implementation should keep the current app moving by adding reusable event structure without forcing a complete rewrite.

## Primary Users

### Bidder

A donor or event guest who wants to bid from a phone with minimal friction.

Needs:
- Fast phone verification.
- Optional email capture for receipts and future communication.
- Event-level item browsing.
- Search, categories, and favorites/watchlist.
- Quick bid and max bid.
- Clear winning/outbid status.
- SMS reminders.
- Simple checkout.
- Clear pickup or delivery instructions.

### Admin

An organizer who builds the auction, monitors activity, closes items, collects payments, and reconciles pickup/fulfillment.

Needs:
- Reusable organizations and events.
- One default close time per event, with item-level override close times.
- Item creation with images, fair market value, starting bid, bid increment, buy-now price, category, donor/source, fulfillment notes, and optional shipping/pickup rules.
- Live event dashboard.
- Winner and unpaid payment queue.
- Reminder controls.
- Fulfillment queue.
- Reports and exports.
- Professional print artifacts.

### Volunteer

An event helper who may only need a limited admin view.

Needs:
- Look up a bidder.
- Confirm payment status.
- Mark an item picked up.
- Resend a payment link.
- See pickup instructions.

## Auction Model

The platform should support:
- Reusable organizations.
- Multiple events per organization.
- One default auction close time per event.
- Item-level close time overrides.
- Hybrid participation: in-person QR scans and remote bidding.
- Optional buy-now items.
- Optional categories.
- Item fulfillment states.

Recommended future entities:
- `organizations`
- `events`
- `event_admins`
- `categories`
- `items.event_id`
- `items.category_id`
- `items.close_time_override`
- `favorites`
- `payment_requests`
- `notification_log`
- `fulfillment_records`
- `receipts`

## Bidder Experience

### First Visit

1. Bidder scans an item QR code or visits the event link.
2. If not signed in, they see a light registration screen.
3. Required: name and phone.
4. Optional but encouraged: email.
5. Phone verification is required before placing bids.
6. After verification, the bidder returns to the item or event page they came from.

Email capture should be low-friction:
- Do not block initial bidding if email is skipped.
- Ask again at checkout if missing.
- Explain that email is for receipts and auction updates.

### Event Home

The event home should replace the current plain all-items listing.

Must show:
- Event name and nonprofit name.
- Closing countdown.
- Search.
- Category chips.
- Sort controls.
- Item cards with photo, current bid, bid count, time/status, and watch button.
- My status summary: winning, outbid, watched, won, unpaid.

### Item Page

Must show:
- Large item image gallery.
- Lot number.
- Title and description.
- Fair market value.
- Current high bid.
- Next minimum bid.
- Bid count.
- Close time.
- Winning/outbid/closed state.
- Quick bid button.
- Max bid option.
- Buy-now button when available.
- Recent activity.
- Related items/category navigation.

The bid interaction should be calm and confident:
- Confirm amount before placing.
- Show inline success instead of browser alerts.
- Show outbid/winning states immediately.
- If anti-sniping extends time later, explain it in human terms.

### My Bids

The bidder needs a dedicated status page:
- Winning now.
- Outbid.
- Watching.
- Won.
- Unpaid.
- Paid.
- Ready for pickup.

This page is critical for hybrid events because guests should not need to remember which QR codes they scanned.

## Payment Experience

Silent Bid Buddy should support both payment modes:

### Item-by-Item Checkout

Useful when items close at different times or buy-now is used.

Flow:
1. Item closes or buy-now is selected.
2. Payment request is created.
3. Bidder receives SMS payment link.
4. Bidder pays for that item.
5. Item moves to paid/fulfillment queue.

### Combined End-of-Auction Checkout

Default recommended gala flow.

Flow:
1. Main auction closes.
2. System groups all won items by bidder.
3. One payment request is created per bidder.
4. Bidder receives SMS link to a consolidated checkout.
5. Checkout shows items, optional fees, optional donation add-on, and total.
6. Payment completion updates every item in the fulfillment queue.

### Future Auto-Charge

Auto-charge should be a later phase. It requires clearer consent, saved payment methods, receipt policy, dispute handling, and stronger Stripe integration. For now, payment links and reminders are safer.

## Reminder System

Required reminder types:
- Phone verification code.
- Outbid alert.
- Winning status confirmation.
- Auction ending soon for watched or active bid items.
- Winner notification.
- Payment reminder.
- Pickup reminder.
- Admin summary after closeout.

Each notification should be logged with:
- Recipient.
- Channel.
- Message type.
- Related event/item/payment request.
- Status.
- Sent time.
- Error message if failed.

## Admin Experience

### Dashboard

The dashboard should be operational, not decorative.

Top priority panels:
- Gross raised / committed.
- Active bidders.
- Bids placed.
- Items without bids.
- Hot items.
- Auction closing countdown.
- Unpaid winners.
- Payment completion rate.
- Pickup completion rate.

### Event Builder

Admins should be able to configure:
- Organization.
- Event name.
- Event date.
- Default auction open time.
- Default auction close time.
- Time zone.
- Public event URL.
- Brand colors/logo.
- Payment mode default: combined, item-by-item, or both.
- Reminder settings.

### Item Management

Item creation should include:
- Lot number.
- Title.
- Description.
- Category.
- Image/gallery.
- Starting bid.
- Minimum increment.
- Fair market value.
- Buy-now price.
- Donor/source.
- Fulfillment notes.
- Pickup/shipping settings.
- Close time override.
- Print artifact generation.

### Closeout

Closeout should be a guided checklist:
- Confirm auction close.
- Resolve items with no bids.
- Generate winners.
- Create payment requests.
- Send winner texts.
- Monitor unpaid winners.
- Mark paid items ready for pickup.
- Export reports.

### Fulfillment

The fulfillment queue should show:
- Item.
- Winner.
- Phone/email.
- Payment status.
- Pickup status.
- Notes.
- Actions: resend payment link, mark paid manually, mark picked up, view receipt.

## Printed Artifacts

The current document generator should become a real print/PDF system.

Artifacts:
- Table tent with QR code.
- Item sheet.
- Catalog page.
- QR code label sheet.
- Checkout/pickup instruction sheet.
- Winner receipt.
- Admin closeout report.
- Fulfillment/pickup list.

Print direction:
- Warm nonprofit identity, not generic tech.
- Big QR code.
- Clear lot number.
- Short bidding instructions.
- Item title and image.
- Current/default close time.
- Consistent brand footer.

## AI Item Enrichment

Silent Bid Buddy should offer an assisted item creation flow for admins who receive rough donor submissions. The admin should be able to enter an item title, donor notes, value, restrictions, and optional reference photos, then generate:
- A polished donor-facing title.
- A rich auction description.
- A concise catalog/PDF summary.
- A professional image when no good photo is available.
- Suggested category, starting bid, increment, and buy-now price.

Generated images should avoid embedded text. Silent Bid Buddy should place wording, lot numbers, QR codes, and event branding in the app/PDF template where typography is reliable and editable.

Description improvement should happen before image generation. Better bidder copy creates a better creative brief for the generated image, and it also helps admins turn sparse donor notes into copy that makes the item feel more valuable, memorable, and easy to bid on.

The admin must always preview and approve generated copy and imagery before publishing. The system should preserve original donor notes for auditability and make regeneration easy when the tone, category, or restrictions are wrong.

## Design Direction

The product should feel:
- Friendly.
- Cheerful.
- Trustworthy.
- Calm under pressure.
- Clean and operational.

Avoid:
- Generic blue dashboard styling.
- Heavy gradients.
- Overly cute visuals.
- Browser alerts for core flows.
- Crowded nested cards.
- Jargon like "proxy" without plain-language explanation.

Preferred UI language:
- Soft warm background.
- Crisp white surfaces.
- Deep ink text.
- Fresh accent colors used sparingly.
- Clear icon buttons.
- Stable mobile layouts.
- Large, confident bid/payment actions.
- Dense but readable admin tables.

## Competitive Parity Checklist

Match Givebutter:
- Event pages.
- Mobile bidding.
- Favorites/watchlist.
- Item categories.
- Real-time updates.
- Bid notifications.
- Automated/max bidding.
- Buy-now.
- Payment links/reminders.
- Winner checkout.
- Payment records.
- Fulfillment tracking.
- QR codes.
- Receipts.

Beat Givebutter:
- Faster in-room QR-to-bid path.
- Better auction-night admin closeout.
- Better volunteer pickup mode.
- Cleaner printed table materials.
- Less clutter for small nonprofits.
- Clearer unpaid-winner chase flow.

## Implementation Phases

### Phase 1: Foundation And Bidder Flow

Goal: make the bidder experience feel like a real event.

Work:
- Add event model with default close time.
- Attach items to an event.
- Add optional email to registration.
- Replace items list with event home.
- Add categories/search/sort.
- Add my bids page.
- Replace bid alerts with inline toast UI.
- Add real max bidding.

### Phase 2: Admin Command Center

Goal: make admins feel in control before, during, and after the auction.

Work:
- Redesign admin dashboard.
- Add event settings.
- Add closeout checklist.
- Add unpaid winners queue.
- Add fulfillment queue.
- Add reminder controls.
- Add volunteer-friendly pickup view.

### Phase 3: Payments And Reminders

Goal: support both item-by-item and combined checkout.

Work:
- Create payment request model.
- Build consolidated checkout.
- Generate payment links.
- Add reminder scheduling.
- Improve webhook reconciliation.
- Add receipt generation.

### Phase 4: Print/PDF System

Goal: make the printed materials look professional.

Work:
- Replace HTML-only "PDF" language with true print artifacts.
- Build table tent and item sheet templates.
- Generate real PDFs or print-ready HTML with accurate naming.
- Add batch generation.
- Add admin preview/download flow.

### Phase 5: Reusable Platform Hardening

Goal: make the app safe for many organizations and events.

Work:
- Organization/account model.
- Admin roles.
- Event switching.
- Brand settings.
- Reporting exports.
- Security review.
- Load/performance checks.

## First Build Slice

The first code slice should be:
1. Add event/event settings support in the schema and helper layer.
2. Seed or auto-create a default event for existing items.
3. Update bidder item browsing to behave like an event page.
4. Add optional email capture to bidder registration and user storage.
5. Add a visual refresh foundation with warm nonprofit design tokens.

This slice gives the whole app a better product spine without blocking later work.
