# Per-Event Stripe Account Configuration
**Silent Bid Buddy**

---

## Overview

Each event can now use its own Stripe account for payment processing. This allows:

- **Different nonprofits** to use their own Stripe accounts (white-label)
- **Events to process payments** through the organization's account
- **Flexible payment routing** without sharing API keys
- **Fallback support** - if no custom keys configured, uses global account

---

## How It Works

### Automatic Detection
When a user checks out an item:

1. The system fetches the item's event_id
2. Looks up event-specific Stripe keys in `event_settings` table
3. If found: uses those keys for payment processing
4. If not found: automatically falls back to global Stripe keys

**No manual intervention needed** - payment processing uses the correct account automatically.

### Three-Tier Key System

```
User Checkout
    ↓
Item → Event ID
    ↓
Check event_settings table
    ↓
Event has custom keys? → YES → Use event-specific keys
    ↓ NO
Use global keys (STRIPE_SECRET_KEY / STRIPE_PUBLISHABLE_KEY)
```

---

## Configuration

### Setting Up Event-Specific Stripe Keys

**Super Admin Only** - Only super admins (is_super_admin = 1) can configure Stripe keys.

#### API Endpoint: `POST /api/admin/update-event-stripe-settings.php`

**Request:**
```json
{
  "event_id": 1,
  "stripe_account_id": "acct_1234567890",
  "stripe_key_publishable": "pk_live_...",
  "stripe_key_secret": "sk_live_..."
}
```

**Response (Success):**
```json
{
  "status": "ok",
  "message": "Stripe settings updated successfully",
  "event_id": 1,
  "stripe_account_id": "acct_1234567890"
}
```

**Validation:**
- Both `stripe_key_publishable` and `stripe_key_secret` required together
- Publishable key must start with `pk_`
- Secret key must start with `sk_`
- Event must exist
- Super admin only

#### To Clear Custom Settings

Send empty values to revert to global keys:

```json
{
  "event_id": 1,
  "stripe_key_publishable": "",
  "stripe_key_secret": ""
}
```

Response:
```json
{
  "status": "ok",
  "message": "Stripe settings cleared. Event will use global Stripe account."
}
```

---

## Viewing Configuration

### API Endpoint: `GET /api/admin/get-event-stripe-settings.php?event_id=1`

**Response (Custom Keys Configured):**
```json
{
  "status": "ok",
  "event_id": 1,
  "event_name": "Spring Giving Gala",
  "using_custom_keys": true,
  "stripe_account_id": "acct_1234567890",
  "stripe_key_publishable": "pk_live_51RfnHU...",
  "stripe_key_secret": "sk_live_51RfnHU..."
}
```

**Response (Using Global Keys):**
```json
{
  "status": "ok",
  "event_id": 1,
  "event_name": "Spring Giving Gala",
  "using_custom_keys": false,
  "message": "Using global Stripe account",
  "stripe_key_publishable": null,
  "stripe_key_secret": null
}
```

---

## Implementation Details

### Database Schema

**Table:** `event_settings`

| Column | Type | Purpose |
|--------|------|---------|
| event_id | INT UNSIGNED | Which event these keys are for |
| stripe_account_id | VARCHAR(255) | Stripe account ID (e.g., acct_...) |
| stripe_key_publishable | VARCHAR(255) | Stripe publishable key (pk_...) |
| stripe_key_secret | VARCHAR(255) | Stripe secret key (sk_...) |

### Code Integration Points

#### 1. **Checkout Session Creation** (`stripe-utils.php`)

```php
function createCheckoutSession($item_id, $user_id, $amount, $item_title, $user_email = '') {
    // Get event_id from item
    $item = dbGetRow("SELECT id, event_id FROM items WHERE id = ?", [(int)$item_id]);
    
    // Get event-specific Stripe keys
    $stripe_keys = getEventStripeKeys($item['event_id']);
    
    // Use event-specific secret key for API calls
    $response = callStripeAPI('/v1/checkout/sessions', $session_data, 'POST', $stripe_keys['secret_key']);
    
    // Return event-specific public key to frontend
    return ['public_key' => $stripe_keys['public_key'], ...];
}
```

#### 2. **Webhook Handling** (`webhook.php`)

```php
// Get event_id from session metadata
$event_id = (int)($session['metadata']['event_id'] ?? 0);

// Verify signature with event-specific secret (if available)
if (!verifyStripeSignature($payload, $signature, $event_id)) {
    // Invalid signature
}
```

#### 3. **Helper Function** (`stripe-utils.php`)

```php
function getEventStripeKeys($event_id) {
    // Query event_settings for event-specific keys
    $event_settings = dbGetRow(
        "SELECT stripe_key_publishable, stripe_key_secret 
         FROM event_settings WHERE event_id = ?",
        [(int)$event_id]
    );
    
    // Return custom keys if configured, else fall back to global
    if ($event_settings && !empty($event_settings['stripe_key_publishable'])) {
        return [
            'public_key' => $event_settings['stripe_key_publishable'],
            'secret_key' => $event_settings['stripe_key_secret']
        ];
    }
    
    return [
        'public_key' => STRIPE_PUBLISHABLE_KEY,
        'secret_key' => STRIPE_SECRET_KEY
    ];
}
```

---

## Workflow: Setting Up an Event with Custom Stripe Account

### Step 1: Get Stripe Keys
Obtain the Stripe API keys from the organization's Stripe account:
- Publishable key (starts with `pk_`)
- Secret key (starts with `sk_`)

### Step 2: Configure via API or Admin Panel
**Using API:**
```bash
curl -X POST https://silentbidbuddy.com/api/admin/update-event-stripe-settings.php \
  -H "Content-Type: application/json" \
  -H "Cookie: session_token=YOUR_SUPER_ADMIN_TOKEN" \
  -d '{
    "event_id": 1,
    "stripe_account_id": "acct_1234567890",
    "stripe_key_publishable": "pk_live_...",
    "stripe_key_secret": "sk_live_..."
  }'
```

### Step 3: Verify Configuration
```bash
curl "https://silentbidbuddy.com/api/admin/get-event-stripe-settings.php?event_id=1" \
  -H "Cookie: session_token=YOUR_SUPER_ADMIN_TOKEN"
```

### Step 4: Test Payment
Place a test bid and proceed to checkout. The system will:
1. Detect the event_id from the item
2. Load the event-specific Stripe keys
3. Create a checkout session using those keys
4. Display the Stripe payment form

---

## Key Features

✓ **Automatic Detection** - No code changes needed per event  
✓ **Fallback Support** - Gracefully falls back to global keys  
✓ **API-Driven** - Configure via REST endpoints  
✓ **Secure** - Only super admins can configure  
✓ **Webhook-Aware** - Supports event-specific webhook verification  
✓ **Metadata Tracking** - Event_id included in checkout sessions  
✓ **Zero Downtime** - Configure without restarting app

---

## Troubleshooting

### "Stripe configuration not available"
- Event doesn't have custom Stripe keys configured
- Global STRIPE_SECRET_KEY not set
- Solution: Configure event-specific keys OR set global keys in config.php

### "Invalid secret key format"
- Secret key doesn't start with `sk_`
- Solution: Get the correct secret key from Stripe dashboard

### "Invalid publishable key format"
- Publishable key doesn't start with `pk_`
- Solution: Ensure you're using the publishable key, not the secret key

### Payment fails at checkout
- Event has incorrect Stripe keys
- Stripe account has insufficient permissions
- Solution: Verify keys are correct and account is active

### Webhook not processing
- Webhook secret doesn't match
- Solution: For event-specific accounts, ensure webhook is configured in Stripe dashboard with correct endpoint URL

---

## Security Best Practices

1. **Never commit API keys** - Store in database via admin API only
2. **Use environment variables** for global keys (config.php)
3. **Restrict to super admins** - Only super admins can configure
4. **Validate key format** - API validates pk_/sk_ prefixes
5. **Use HTTPS** - All API calls must be over HTTPS
6. **Rotate keys periodically** - Update keys in Stripe dashboard
7. **Monitor transactions** - Review all payments in Stripe dashboard

---

## Database Migration

The `event_settings` table columns are already in place from migration 004:
- `stripe_account_id` - For reference/documentation
- `stripe_key_publishable` - Event-specific public key
- `stripe_key_secret` - Event-specific secret key

No additional migrations needed.

---

## Examples

### Example 1: Nonprofit with Multiple Stripe Accounts

**Scenario:** Organization has separate Stripe accounts for tax purposes
- Event A: Uses Stripe Account 1 (Organization's main account)
- Event B: Uses Stripe Account 2 (Affiliate organization account)

**Solution:** 
1. Configure Event A with Account 1 keys
2. Configure Event B with Account 2 keys
3. All payments automatically route to correct account

### Example 2: White-Label Platform

**Scenario:** SBB hosts multiple nonprofits, each with their own Stripe account

**Solution:**
1. Each nonprofit organization configures their event with their own keys
2. Payments route to each nonprofit's Stripe account
3. No sharing of API keys between organizations

### Example 3: Gradual Migration

**Scenario:** Moving from global to event-specific Stripe accounts

**Solution:**
1. Start with global keys (status quo)
2. Configure Event A with custom keys
3. Payments for Event A use custom account
4. Event B continues using global account
5. Gradually migrate other events

---

## Production Checklist

- [ ] Verify event_settings table exists with Stripe columns
- [ ] Test with event-specific keys configured
- [ ] Test with no custom keys (fallback to global)
- [ ] Verify webhook receives event_id in metadata
- [ ] Test payment flow end-to-end
- [ ] Confirm transaction appears in correct Stripe account
- [ ] Monitor error logs for Stripe API issues
- [ ] Test fallback when event_settings missing

---

**Last Updated:** June 15, 2026  
**Version:** 1.0
