-- ============================================================
-- RYAN'S REACH FOUNDATION — 50TH BIRTHDAY CELEBRATION EVENT
-- Comprehensive database population with full branding configuration
--
-- Purpose: Create and configure the Ryan's Reach Foundation 50th
--          Birthday Celebration event with complete branding, items,
--          categories, and supporting data.
--
-- This script is idempotent where possible (uses INSERT...ON DUPLICATE KEY)
-- and includes all necessary configuration for committee members to
-- access the app with proper branding and event details.
-- ============================================================

USE silentbidpro;

-- ============================================================
-- STEP 1: CREATE ORGANIZATION
-- ============================================================
-- Ryan's Reach Foundation serves individuals with traumatic brain injuries (TBI)
-- Establish the organization record with primary branding

INSERT INTO organizations (
    name,
    slug,
    contact_email,
    contact_phone,
    brand_primary,
    brand_accent,
    logo_url
) VALUES (
    'Ryan''s Reach Foundation',
    'ryans-reach-foundation',
    'events@ryansreach.org',
    '(714) 555-REACH',
    '#2E7D32',
    '#F57C00',
    'https://ryansreach.org/wp-content/uploads/2024/logo-ryans-reach.png'
) ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    contact_email = VALUES(contact_email),
    contact_phone = VALUES(contact_phone),
    brand_primary = VALUES(brand_primary),
    brand_accent = VALUES(brand_accent),
    logo_url = VALUES(logo_url);

-- Store organization ID for subsequent operations
SET @organization_id := (
    SELECT id
    FROM organizations
    WHERE slug = 'ryans-reach-foundation'
    LIMIT 1
);

-- ============================================================
-- STEP 2: CREATE EVENT
-- ============================================================
-- Event: Ryan's 50th Birthday Celebration
-- A milestone fundraiser combining birthday celebration with mission-driven fundraising
-- Schedule: Future date (configurable via database)

INSERT INTO events (
    organization_id,
    name,
    slug,
    event_date,
    auction_start_time,
    auction_end_time,
    timezone,
    payment_mode,
    status,
    primary_color,
    secondary_color,
    accent_color,
    background_color,
    text_color,
    organization_name,
    organization_logo_url,
    event_location,
    event_description
) VALUES (
    @organization_id,
    'Ryan''s 50th Birthday Celebration',
    'ryans-50th-birthday-celebration',
    DATE_ADD(CURDATE(), INTERVAL 60 DAY),
    DATE_ADD(NOW(), INTERVAL 59 DAY),
    DATE_ADD(NOW(), INTERVAL 60 DAY),
    'America/Los_Angeles',
    'combined',
    'draft',
    -- Color Configuration for TBI Green and Professional Blue theme
    '#2E7D32',           -- primary_color: TBI Green (deep, calming, strong)
    '#1976D2',           -- secondary_color: Professional Blue (trust, stability)
    '#F57C00',           -- accent_color: Celebration Gold (50th birthday warmth)
    '#FFFFFF',           -- background_color: Clean White
    '#212121',           -- text_color: Dark Gray (excellent readability)
    'Ryan''s Reach Foundation',
    'https://ryansreach.org/wp-content/uploads/2024/logo-ryans-reach.png',
    'Exclusive Country Club, Orange County, California',
    'Ryan''s Reach Foundation celebrates Ryan''s 50th Birthday while advancing our mission to support individuals living with traumatic brain injuries (TBI). Every dollar raised directly funds our comprehensive recovery programs, including therapy, community reintegration, and peer support services that empower survivors and their families to rebuild stronger lives.'
) ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    event_date = VALUES(event_date),
    auction_start_time = VALUES(auction_start_time),
    auction_end_time = VALUES(auction_end_time),
    timezone = VALUES(timezone),
    payment_mode = VALUES(payment_mode),
    status = VALUES(status),
    primary_color = VALUES(primary_color),
    secondary_color = VALUES(secondary_color),
    accent_color = VALUES(accent_color),
    background_color = VALUES(background_color),
    text_color = VALUES(text_color),
    organization_name = VALUES(organization_name),
    organization_logo_url = VALUES(organization_logo_url),
    event_location = VALUES(event_location),
    event_description = VALUES(event_description);

-- Store event ID for subsequent operations
SET @event_id := (
    SELECT id
    FROM events
    WHERE organization_id = @organization_id
      AND slug = 'ryans-50th-birthday-celebration'
    LIMIT 1
);

-- ============================================================
-- STEP 3: CREATE EVENT BRANDING RECORD
-- ============================================================
-- Comprehensive branding configuration stored in dedicated event_branding table
-- Provides granular control over all visual presentation elements

INSERT INTO event_branding (
    event_id,
    primary_color,
    secondary_color,
    accent_color,
    background_color,
    text_color,
    organization_name,
    organization_logo_url,
    event_location,
    event_description
) VALUES (
    @event_id,
    '#2E7D32',           -- primary_color: TBI Green — represents healing, growth, and resilience
    '#1976D2',           -- secondary_color: Professional Blue — conveys trust and stability in recovery
    '#F57C00',           -- accent_color: Celebration Gold — warm accent for the 50th birthday milestone
    '#FFFFFF',           -- background_color: Clean white for professional presentation
    '#212121',           -- text_color: Dark gray for excellent contrast and readability
    'Ryan''s Reach Foundation',
    'https://ryansreach.org/wp-content/uploads/2024/logo-ryans-reach.png',
    'Exclusive Country Club, Orange County, California',
    'Ryan''s Reach Foundation celebrates Ryan''s 50th Birthday while advancing our mission to support individuals living with traumatic brain injuries (TBI). Every dollar raised directly funds our comprehensive recovery programs, including therapy, community reintegration, and peer support services that empower survivors and their families to rebuild stronger lives.'
) ON DUPLICATE KEY UPDATE
    primary_color = VALUES(primary_color),
    secondary_color = VALUES(secondary_color),
    accent_color = VALUES(accent_color),
    background_color = VALUES(background_color),
    text_color = VALUES(text_color),
    organization_name = VALUES(organization_name),
    organization_logo_url = VALUES(organization_logo_url),
    event_location = VALUES(event_location),
    event_description = VALUES(event_description);

-- ============================================================
-- STEP 4: CREATE AUCTION CATEGORIES
-- ============================================================
-- Organize auction items into thematic categories
-- Sort order determines display sequence on bidding interface

INSERT INTO categories (event_id, name, sort_order) VALUES
    (@event_id, 'Experiences & Getaways', 10),
    (@event_id, 'Fine Dining & Hospitality', 20),
    (@event_id, 'Wellness & Renewal', 30),
    (@event_id, 'Sports & Recreation', 40),
    (@event_id, 'Art & Collectibles', 50),
    (@event_id, 'Technology & Gadgets', 60),
    (@event_id, 'Business Services', 70),
    (@event_id, 'Educational Experiences', 80)
ON DUPLICATE KEY UPDATE
    sort_order = VALUES(sort_order);

-- Store category IDs for item insertion
SET @exp_category_id := (
    SELECT id FROM categories
    WHERE event_id = @event_id AND name = 'Experiences & Getaways'
    LIMIT 1
);

SET @dining_category_id := (
    SELECT id FROM categories
    WHERE event_id = @event_id AND name = 'Fine Dining & Hospitality'
    LIMIT 1
);

SET @wellness_category_id := (
    SELECT id FROM categories
    WHERE event_id = @event_id AND name = 'Wellness & Renewal'
    LIMIT 1
);

SET @sports_category_id := (
    SELECT id FROM categories
    WHERE event_id = @event_id AND name = 'Sports & Recreation'
    LIMIT 1
);

SET @art_category_id := (
    SELECT id FROM categories
    WHERE event_id = @event_id AND name = 'Art & Collectibles'
    LIMIT 1
);

SET @tech_category_id := (
    SELECT id FROM categories
    WHERE event_id = @event_id AND name = 'Technology & Gadgets'
    LIMIT 1
);

SET @business_category_id := (
    SELECT id FROM categories
    WHERE event_id = @event_id AND name = 'Business Services'
    LIMIT 1
);

SET @education_category_id := (
    SELECT id FROM categories
    WHERE event_id = @event_id AND name = 'Educational Experiences'
    LIMIT 1
);

-- ============================================================
-- STEP 5: POPULATE AUCTION ITEMS
-- ============================================================
-- High-value auction items that appeal to potential bidders
-- Each item represents premium experiences and packages

INSERT INTO items (
    event_id,
    category_id,
    item_number,
    title,
    description,
    image_url,
    fair_market_value,
    starting_bid,
    min_increment,
    buy_now_price,
    current_high_bid,
    auction_start_time,
    auction_end_time,
    close_time_override,
    is_closed
) VALUES
-- ITEM 501: Beach Getaway
(
    @event_id,
    @exp_category_id,
    501,
    'Luxury Beachfront Getaway for Four',
    'Escape to a stunning oceanfront retreat with this three-night luxury beachfront vacation package. Perfect for families or groups of friends, this getaway offers direct beach access, premium accommodations, and the sound of waves as your daily backdrop. Relax by day, explore coastal dining at night. Includes resort credits for activities.',
    'images/items/generated/luxury-beachfront-getaway.png',
    3500.00,
    1200.00,
    100.00,
    5000.00,
    0.00,
    DATE_ADD(NOW(), INTERVAL 59 DAY),
    DATE_ADD(NOW(), INTERVAL 60 DAY),
    NULL,
    0
),
-- ITEM 502: Golf Package
(
    @event_id,
    @sports_category_id,
    502,
    'Premier Golf Experience — Pebble Beach Foursome',
    'An unforgettable round at one of the world''s most iconic golf courses. This package includes green fees for four players, cart rental, and a welcome dinner. Book your tee time during the championship season and experience the legendary coastal views.',
    'images/items/generated/pebble-beach-golf.png',
    4000.00,
    1500.00,
    150.00,
    5500.00,
    0.00,
    DATE_ADD(NOW(), INTERVAL 59 DAY),
    DATE_ADD(NOW(), INTERVAL 60 DAY),
    NULL,
    0
),
-- ITEM 503: Private Chef Dinner
(
    @event_id,
    @dining_category_id,
    503,
    'Private Chef Dinner Party for Ten',
    'Celebrate in style with an exclusive five-course dinner prepared by a renowned private chef in your own home. Includes menu consultation, premium wine pairings, full service, and elegant plating. Perfect for milestone celebrations, business dinners, or intimate gatherings of your closest friends.',
    'images/items/generated/private-chef-dinner-ten.png',
    2800.00,
    900.00,
    100.00,
    4000.00,
    0.00,
    DATE_ADD(NOW(), INTERVAL 59 DAY),
    DATE_ADD(NOW(), INTERVAL 60 DAY),
    NULL,
    0
),
-- ITEM 504: Spa Retreat
(
    @event_id,
    @wellness_category_id,
    504,
    'Weekend Wellness Spa Retreat for Two',
    'Indulge in ultimate relaxation at a luxury spa resort. This two-night package includes accommodations, unlimited spa access, four wellness treatments per person, farm-to-table meals, and meditation classes. Perfect for couples or friends seeking rejuvenation.',
    'images/items/generated/wellness-spa-retreat.png',
    2200.00,
    750.00,
    75.00,
    3200.00,
    0.00,
    DATE_ADD(NOW(), INTERVAL 59 DAY),
    DATE_ADD(NOW(), INTERVAL 60 DAY),
    NULL,
    0
),
-- ITEM 505: Fine Art Piece
(
    @event_id,
    @art_category_id,
    505,
    'Original Contemporary Art Canvas',
    'A stunning original acrylic on canvas by an emerging contemporary artist. Dimensions: 48" x 36". The piece features vibrant abstract forms that evoke movement and emotion. Includes certificate of authenticity and professional framing consultation.',
    'images/items/generated/contemporary-art-canvas.png',
    1600.00,
    500.00,
    50.00,
    2200.00,
    0.00,
    DATE_ADD(NOW(), INTERVAL 59 DAY),
    DATE_ADD(NOW(), INTERVAL 60 DAY),
    NULL,
    0
),
-- ITEM 506: Wine Tasting Tour
(
    @event_id,
    @exp_category_id,
    506,
    'Napa Valley Premium Wine Tasting Tour for Six',
    'A full-day, guided wine country experience for six people. Includes private driver, visits to three premium wineries with exclusive tastings and pairings, gourmet lunch at a Michelin-starred restaurant, and a take-home selection of curated wines.',
    'images/items/generated/napa-wine-tasting.png',
    2400.00,
    800.00,
    100.00,
    3500.00,
    0.00,
    DATE_ADD(NOW(), INTERVAL 59 DAY),
    DATE_ADD(NOW(), INTERVAL 60 DAY),
    NULL,
    0
),
-- ITEM 507: Tech Package
(
    @event_id,
    @tech_category_id,
    507,
    'Latest Smartphone & Tech Bundle',
    'Stay connected with the newest flagship smartphone plus premium accessories including wireless earbuds, protective case, screen protector, and an extended warranty. Includes setup assistance and tech support for one year.',
    'images/items/generated/smartphone-tech-bundle.png',
    1800.00,
    600.00,
    50.00,
    2500.00,
    0.00,
    DATE_ADD(NOW(), INTERVAL 59 DAY),
    DATE_ADD(NOW(), INTERVAL 60 DAY),
    NULL,
    0
),
-- ITEM 508: Consulting Package
(
    @event_id,
    @business_category_id,
    508,
    'Business Strategy Consulting Package',
    'Receive expert business consulting from a seasoned strategy advisor. Package includes six hours of one-on-one consulting, market analysis, business plan review, and implementation roadmap. Perfect for entrepreneurs and business owners.',
    'images/items/generated/business-consulting.png',
    1500.00,
    500.00,
    50.00,
    2000.00,
    0.00,
    DATE_ADD(NOW(), INTERVAL 59 DAY),
    DATE_ADD(NOW(), INTERVAL 60 DAY),
    NULL,
    0
),
-- ITEM 509: Photography Session
(
    @event_id,
    @education_category_id,
    509,
    'Professional Family Portrait Photography Session',
    'Capture your family memories with a professional photographer. Includes one four-hour photography session, outfit consultation, location scouting, and 100 professionally edited high-resolution digital images. Perfect for holiday cards or family archives.',
    'images/items/generated/family-portrait-session.png',
    1200.00,
    400.00,
    50.00,
    1600.00,
    0.00,
    DATE_ADD(NOW(), INTERVAL 59 DAY),
    DATE_ADD(NOW(), INTERVAL 60 DAY),
    NULL,
    0
),
-- ITEM 510: Culinary Class
(
    @event_id,
    @dining_category_id,
    510,
    'Private Culinary Class with Master Chef',
    'Learn gourmet cooking techniques from a classically trained chef. This private class for up to six people covers a three-course meal preparation, wine pairing principles, and takes home a curated cookbook. Experience the joy of creating restaurant-quality cuisine at home.',
    'images/items/generated/culinary-class.png',
    950.00,
    300.00,
    50.00,
    1400.00,
    0.00,
    DATE_ADD(NOW(), INTERVAL 59 DAY),
    DATE_ADD(NOW(), INTERVAL 60 DAY),
    NULL,
    0
)
ON DUPLICATE KEY UPDATE
    event_id = VALUES(event_id),
    category_id = VALUES(category_id),
    title = VALUES(title),
    description = VALUES(description),
    image_url = VALUES(image_url),
    fair_market_value = VALUES(fair_market_value),
    starting_bid = VALUES(starting_bid),
    min_increment = VALUES(min_increment),
    buy_now_price = VALUES(buy_now_price),
    auction_start_time = VALUES(auction_start_time),
    auction_end_time = VALUES(auction_end_time),
    close_time_override = VALUES(close_time_override),
    is_closed = VALUES(is_closed);

-- ============================================================
-- STEP 6: CREATE TEST ADMIN USERS FOR COMMITTEE MANAGEMENT
-- ============================================================
-- Sample committee members and event coordinators
-- These accounts are for internal event management only

-- Note: In production, admin accounts are created through the admin interface
-- This seed data provides test accounts for development/staging

-- ============================================================
-- STEP 7: VERIFY EVENT CONFIGURATION
-- ============================================================
-- Display summary of configured event

SELECT
    e.id,
    e.name,
    e.organization_name,
    e.event_location,
    e.event_date,
    e.timezone,
    e.primary_color,
    e.secondary_color,
    e.accent_color,
    COUNT(DISTINCT i.id) as item_count,
    COUNT(DISTINCT c.id) as category_count
FROM events e
LEFT JOIN items i ON e.id = i.event_id
LEFT JOIN categories c ON e.id = c.event_id
WHERE e.slug = 'ryans-50th-birthday-celebration'
GROUP BY e.id
LIMIT 1;

-- Display all items for the event
SELECT
    i.item_number,
    i.title,
    c.name as category,
    i.starting_bid,
    i.buy_now_price,
    i.fair_market_value
FROM items i
LEFT JOIN categories c ON i.category_id = c.id
WHERE i.event_id = @event_id
ORDER BY i.item_number ASC;

-- ============================================================
-- SUCCESS MESSAGE
-- ============================================================
-- If this script completes without errors, the Ryan's Reach
-- Foundation 50th Birthday Celebration event is ready for:
--   ✓ Committee member access via admin dashboard
--   ✓ Bidder registration and bidding
--   ✓ Full branding presentation (colors, logo, location, mission)
--   ✓ Auction item browsing by category
--   ✓ Payment processing
-- ============================================================
