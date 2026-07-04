# Ryan's Reach Foundation Event Setup Guide

## Quick Start

This guide explains how to use the Ryan's Reach Foundation 50th Birthday Celebration database setup script.

## File Location

```
/Users/chipmcallister/Projects/silentbidpro/sql/populate-ryans-reach-event.sql
```

## What Gets Created

### 1. Organization
- **Name:** Ryan's Reach Foundation
- **Slug:** ryans-reach-foundation
- **Mission:** Support for individuals with traumatic brain injuries (TBI)

### 2. Event
- **Name:** Ryan's 50th Birthday Celebration
- **Date:** 60 days from execution
- **Location:** Exclusive Country Club, Orange County, California
- **Status:** Draft (ready for activation)

### 3. Branding Color Palette

| Element | Color | Hex | Purpose |
|---------|-------|-----|---------|
| Primary | TBI Green | #2E7D32 | Healing, growth, resilience |
| Secondary | Professional Blue | #1976D2 | Trust, stability |
| Accent | Celebration Gold | #F57C00 | 50th birthday warmth |
| Background | White | #FFFFFF | Clean presentation |
| Text | Dark Gray | #212121 | Excellent readability |

### 4. Auction Categories (8 Total)
- Experiences & Getaways
- Fine Dining & Hospitality
- Wellness & Renewal
- Sports & Recreation
- Art & Collectibles
- Technology & Gadgets
- Business Services
- Educational Experiences

### 5. Auction Items (10 Total)

| # | Item | Value | Start | Buy Now | Category |
|---|------|-------|-------|---------|----------|
| 501 | Luxury Beachfront Getaway for Four | $3,500 | $1,200 | $5,000 | Experiences |
| 502 | Pebble Beach Golf Foursome | $4,000 | $1,500 | $5,500 | Sports |
| 503 | Private Chef Dinner (10 people) | $2,800 | $900 | $4,000 | Dining |
| 504 | Wellness Spa Retreat (2 people) | $2,200 | $750 | $3,200 | Wellness |
| 505 | Contemporary Art Canvas | $1,600 | $500 | $2,200 | Art |
| 506 | Napa Wine Tasting Tour (6 people) | $2,400 | $800 | $3,500 | Experiences |
| 507 | Smartphone & Tech Bundle | $1,800 | $600 | $2,500 | Technology |
| 508 | Business Strategy Consulting | $1,500 | $500 | $2,000 | Business |
| 509 | Family Portrait Photography | $1,200 | $400 | $1,600 | Education |
| 510 | Culinary Class with Master Chef | $950 | $300 | $1,400 | Dining |

**Total Catalog Value: $17,450**

## How to Execute

### Local Development
```bash
mysql -u root -p silentbidpro < sql/populate-ryans-reach-event.sql
```

### DigitalOcean Production
```bash
# SSH into droplet
ssh root@your-droplet-ip

# Navigate to project
cd /var/www/silentbidpro

# Execute script
mysql -u root -p silentbidpro < sql/populate-ryans-reach-event.sql
```

## Verification

After running the script, verify success:

```sql
-- Check event was created
SELECT name, event_location, primary_color, status 
FROM events 
WHERE slug = 'ryans-50th-birthday-celebration';

-- Check all items
SELECT item_number, title, starting_bid, buy_now_price
FROM items
WHERE event_id = (SELECT id FROM events WHERE slug = 'ryans-50th-birthday-celebration')
ORDER BY item_number;

-- Check branding
SELECT organization_name, event_location, primary_color, secondary_color, accent_color
FROM event_branding
WHERE event_id = (SELECT id FROM events WHERE slug = 'ryans-50th-birthday-celebration');
```

## Key Features

### Idempotent Design
- Safe to run multiple times
- Uses `INSERT...ON DUPLICATE KEY UPDATE`
- Updates existing records if re-executed

### Complete Branding
- All color palette settings in events table
- Separate event_branding record for detailed configuration
- Organization logo and mission statement integrated

### Production Ready
- Comprehensive inline documentation
- Clear section headers
- Foreign key relationships properly defined
- Timestamps for audit trail
- Verification queries included

## Next Steps After Execution

1. **Activate Event**
   - Go to admin.php
   - Change event status from 'draft' to 'open'

2. **Upload Item Images**
   - Add images to `/images/items/generated/`
   - Files should match paths in database:
     - `luxury-beachfront-getaway.png`
     - `pebble-beach-golf.png`
     - etc.

3. **Configure Payment**
   - Set Stripe keys in event_settings
   - Configure webhook endpoints

4. **Set Notifications**
   - Configure SMS templates in admin panel
   - Test outbid and winner notifications

5. **Invite Committee**
   - Create admin accounts for committee members
   - Assign manager role for event management
   - Share login credentials securely

6. **Deploy to Production**
   - Push updated code to DigitalOcean
   - Run database migration if needed
   - Verify branding displays correctly

## Troubleshooting

### Script Fails to Run
- Verify MySQL is running: `mysql -u root -p -e "SELECT 1;"`
- Check database exists: `mysql -u root -p -e "USE silentbidpro;"`
- Ensure file permissions: `chmod 644 sql/populate-ryans-reach-event.sql`

### Event Not Showing in Admin
- Verify organization was created: `SELECT * FROM organizations WHERE slug = 'ryans-reach-foundation';`
- Check event_id was assigned: `SELECT id, name FROM events WHERE slug = 'ryans-50th-birthday-celebration';`
- Verify user has proper admin role in admin_events

### Colors Not Displaying
- Verify hex values are stored: `SELECT primary_color, secondary_color FROM events WHERE id = [event_id];`
- Check CSS is loading color values from database
- Clear browser cache and reload

## Support

For issues or questions:
1. Check CLAUDE.md in project root for architectural overview
2. Review database schema in sql/schema.sql
3. See recent migrations in sql/migrations/ for schema changes

---

**Created:** June 24, 2026
**Event Date:** 60 days from execution
**Status:** Production Ready
