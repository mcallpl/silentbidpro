# Silent Bid Buddy — Enterprise Silent Auction Platform

An elegant, mobile-first silent auction platform built for upscale nonprofit galas. Features passwordless SMS authentication, real-time bidding with anti-sniping mechanics, and frictionless Stripe checkout.

## Features

✨ **Mobile-First Design**
- Responsive, production-grade interface optimized for smartphones
- Touch-friendly buttons (48px minimum tap targets)
- Real-time bid updates and countdown timer
- Elegant, high-contrast UI following nonprofit gala aesthetic

🔐 **Secure Authentication**
- Passwordless SMS verification via Twilio
- Session token-based authentication
- Rate limiting and brute-force protection
- Prepared statements and input validation throughout

💰 **Frictionless Bidding**
- One-tap quick bidding at minimum increment
- Proxy bidding with automatic incremental bids
- Anti-sniping "popcorn bidding" extends auctions
- Real-time outbid SMS alerts

💳 **Payment Processing**
- Stripe Checkout integration for secure payments
- Apple Pay, Google Pay, and card support
- Automatic payment processing and status tracking
- Winner notifications with payment links

📊 **Admin Dashboard**
- CLI tool for auction management
- Live monitoring with real-time metrics
- Batch QR code generation for printing
- Automated winner processing

## Project Structure

```
/silentbidbuddy/
├── config.php                 # Configuration & database connection
├── index.php                  # Auth splash screen
├── item.php                   # Main bidding interface
├── checkout.php               # Payment checkout
├── success.php                # Payment confirmation
├── auction.php                # CLI admin tool
├── api/                       # REST API endpoints
│   ├── auth/                  # Authentication
│   ├── bidding/               # Bid placement & live feed
│   ├── checkout/              # Stripe integration
│   └── admin/                 # Admin operations
├── includes/                  # Business logic modules
│   ├── auth.php               # Session & auth helpers
│   ├── bidding.php            # Bid logic & proxy bidding
│   ├── notifications.php      # Twilio SMS
│   ├── stripe-utils.php       # Payment processing
│   ├── auction-engine.php     # Auto-closing & metrics
│   └── db-helpers.php         # Database query wrappers
├── css/                       # Responsive stylesheets
│   ├── main.css               # Core styles
│   └── mobile.css             # Responsive breakpoints
├── js/                        # Frontend JavaScript
│   ├── app.js                 # Auth flow & API comms
│   ├── bidding.js             # Real-time updates & bidding
│   └── stripe-checkout.js     # Stripe integration
├── sql/                       # Database schema
│   └── schema.sql             # MySQL DDL
└── README.md                  # This file
```

## Quick Start

### Prerequisites
- PHP 8.0+
- MySQL 8.0+
- Node.js/npm (optional, for QR code generation library)
- Stripe account
- Twilio account
- Web server (Apache with mod_rewrite or Nginx)

### Local Development Setup

1. **Clone and configure:**
   ```bash
   cd /Users/chipmcallister/Projects/silentbidbuddy
   cp config.php config.php  # Already configured in template
   ```

2. **Create database:**
   ```bash
   mysql -u mcallpl -p amazing123 < sql/schema.sql
   ```

3. **Add credentials to vault:**
   Edit `/vault/secrets.php` with Stripe, Twilio credentials (already configured).

4. **Start local server:**
   ```bash
   php -S localhost:8000
   ```

5. **Test:**
   - Open http://localhost:8000/
   - Enter phone number (use +1-555-0123 for testing)
   - Receive code in test SMS service

### Database Schema

**Users Table**
- `id` (int) — Primary key
- `phone_number` (varchar) — Unique, E.164 format
- `full_name` (varchar) — User display name
- `stripe_customer_id` (varchar) — Stripe customer reference
- `created_at` (datetime) — Account creation timestamp

**Items Table**
- `id` (int) — Primary key
- `item_number` (int) — Unique item identifier (printed on QR codes)
- `title`, `description`, `image_url` — Item details
- `fair_market_value`, `starting_bid`, `min_increment` — Bid rules
- `buy_now_price` (nullable) — Fixed purchase option
- `current_high_bid`, `current_high_bidder_id` — Current winning bid
- `auction_end_time` — When auction closes
- `is_closed` (tinyint) — Locked after time expires

**Bids Table**
- `id` (int) — Primary key
- `item_id`, `user_id` (int) — Foreign keys
- `bid_amount` — This bid amount
- `max_bid_amount` (nullable) — Proxy bidding ceiling
- `created_at` (datetime) — Bid timestamp

**Transactions Table**
- `id` (int) — Primary key
- `user_id`, `item_id` (int) — Foreign keys
- `stripe_payment_intent_id`, `stripe_checkout_session_id` (varchar) — Stripe references
- `amount` (decimal) — Winning bid amount
- `status` (enum) — pending|paid|failed|cancelled
- `created_at` (datetime) — Transaction timestamp

**Sessions Table**
- `id` (int) — Primary key
- `user_id` (int) — Foreign key
- `session_token` (varchar) — Cryptographic token
- `created_at`, `expires_at` (datetime) — Session lifetime
- `ip_address`, `user_agent` — Security tracking

**Verification Codes Table**
- `id` (int) — Primary key
- `phone_number` (varchar) — Phone requesting code
- `code` (varchar) — 6-digit SMS code
- `expires_at` (datetime) — 15-minute expiry
- `attempts` (int) — Brute-force tracking (max 5)
- `is_used` (tinyint) — Prevent reuse

## API Endpoints

### Authentication
- `POST /api/auth/send-code.php` — Send SMS code
- `POST /api/auth/verify-code.php` — Verify code & create session

### Bidding
- `POST /api/bidding/place-bid.php` — Place/update bid
- `GET /api/bidding/get-item.php?id=X` — Fetch item state
- `GET /api/bidding/get-live-feed.php?id=X` — Real-time bid feed

### Checkout
- `POST /api/checkout/create-session.php` — Create Stripe Checkout
- `POST /api/checkout/webhook.php` — Stripe webhook handler

### Admin
- `POST /api/admin/create-item.php` — Create auction item
- `POST /api/admin/close-auction.php` — Close active auctions
- `GET /api/admin/get-metrics.php` — Live metrics

## CLI Commands

### Create Item
```bash
php auction.php item:create
```
Interactive prompts for:
- Title, description, image URL
- Starting bid, minimum increment
- Auction duration (hours/minutes/seconds)
- Optional buy-now price

### Generate QR Codes
```bash
php auction.php qr:generate
```
Generates high-resolution PNG QR codes in `qr_codes/` directory, print-ready at 300 DPI.

### Live Monitoring
```bash
php auction.php monitor:live
```
Real-time dashboard with:
- Active items count
- Bidders in last hour
- Total bids and funds raised
- High-traffic items
- Recent activity log

### Close Auction
```bash
php auction.php auction:close
```
Manually close all active auctions:
- Marks items as closed
- Processes winners
- Sends winner notifications
- Creates payment transactions

## Authentication Flow

1. **Splash Screen**
   - User enters phone number
   - POST to `/api/auth/send-code.php`
   - Twilio sends 6-digit SMS code

2. **Verification**
   - User enters code
   - POST to `/api/auth/verify-code.php`
   - System creates user if new
   - Session token generated (32-byte hex)
   - Token stored in localStorage

3. **Protected Requests**
   - All API calls include `Authorization: Bearer <token>` header
   - Backend validates token & expiry
   - Implicit CSRF protection (header-based)

## Bidding Logic

### Minimum Bid Rules
- First bid ≥ starting_bid
- Subsequent bids ≥ current_high_bid + min_increment
- Error returned if amount too low

### Proxy Bidding
- User can set `max_bid_amount`
- System automatically bids on outbids
- Stops at user's maximum or when outbid by higher amount

### Anti-Sniping (Popcorn Bidding)
- Bid within final 2 minutes → automatically extend by 2 minutes
- Drives excitement and maximizes donations
- Prevents "last-second steals"

### Outbid Alerts
- When user loses high bid status
- Twilio SMS sent with item link
- Format: "You've been outbid on [Item]! Bid again: [URL]"

## Payment Processing

### Winner Flow
1. Auction ends → system marks item closed
2. Winner receives SMS: "Congratulations! You won [Item] for $X. Complete payment: [URL]"
3. Winner clicks link → `/checkout.php?item_id=X`
4. Page calls `/api/checkout/create-session.php`
5. Stripe Checkout modal loads → payment options
6. Payment processed → Stripe webhook `/api/checkout/webhook.php`
7. Transaction marked paid → winner redirected to `/success.php`

### Stripe Integration
- No local payment method storage (PCI compliant)
- Uses Stripe Checkout for secure flow
- Webhook verifies `checkout.session.completed` event
- Supports Apple Pay, Google Pay, card

## Security Considerations

### Authentication
- ✅ Passwordless SMS prevents phishing
- ✅ Rate limiting (5 codes/min per phone)
- ✅ Brute-force protection (5 attempts max)
- ✅ Session expiry (30 days)

### Data Integrity
- ✅ All SQL via prepared statements
- ✅ MySQLi with bind_param
- ✅ Input validation on all boundaries
- ✅ XSS prevention (htmlspecialchars escaping)

### CSRF Protection
- ✅ Authorization header required (not cookie-based form)
- ✅ Implicit protection for stateless APIs

### Payment Security
- ✅ Stripe handles card data (PCI DSS Level 1)
- ✅ No sensitive data in logs
- ✅ HTTPS required in production

## Deployment to Digital Ocean

1. **SSH to server:**
   ```bash
   ssh root@64.227.108.128
   ```

2. **Clone repository:**
   ```bash
   cd /var/www
   git clone https://github.com/chipmcallister/silentbidbuddy.git
   cd silentbidbuddy
   ```

3. **Create local config:**
   ```bash
   cp config.php config.local.php
   # Edit config.local.php with server-specific settings
   ```

4. **Initialize database:**
   ```bash
   mysql -u mcallpl -p < sql/schema.sql
   ```

5. **Set permissions:**
   ```bash
   chown -R www-data:www-data /var/www/silentbidbuddy
   chmod 755 /var/www/silentbidbuddy
   chmod 644 /var/www/silentbidbuddy/*.php
   mkdir -p logs uploads qr_codes
   chmod 755 logs uploads qr_codes
   ```

6. **Configure Apache/Nginx:**
   - Enable mod_rewrite
   - Point DocumentRoot to `/var/www/silentbidbuddy`
   - Enable .htaccess in Apache config

7. **Test:**
   ```bash
   curl https://silentbidbuddy.yourdomain.com/
   ```

## Testing Checklist

- [ ] SMS code delivery (check Twilio logs)
- [ ] Bid placement and validation
- [ ] Anti-sniping timer extension
- [ ] Outbid notifications
- [ ] Real-time bid feed updates
- [ ] Stripe Checkout flow
- [ ] Payment webhook processing
- [ ] Winner notifications
- [ ] QR code generation
- [ ] Mobile responsiveness (test on iPhone 13, Android)
- [ ] Countdown timer accuracy
- [ ] Session expiration

## Cron Tasks

For production, add these cron jobs:

```bash
# Close expired auctions every 30 seconds
* * * * * /usr/bin/php /var/www/silentbidbuddy/api/admin/close-auction.php >/dev/null 2>&1

# Cleanup old verification codes and sessions (daily)
0 2 * * * /usr/bin/php -r 'require("/var/www/silentbidbuddy/config.php"); require("/var/www/silentbidbuddy/includes/auction-engine.php"); cleanupExpiredRecords();' >/dev/null 2>&1
```

## Performance Optimization

- Database indices on phone_number, item_number, auction_end_time
- Image lazy-loading on item gallery
- CSS/JS minification for production
- Gzip compression via .htaccess
- Browser caching headers set
- AJAX polling (not websockets) for lower server load

## Support & Contributing

For issues, questions, or feature requests, contact Chip McCallister or file an issue on GitHub.

## License

Private. All rights reserved.

---

**Built with 💜 for nonprofit gala magic.**
