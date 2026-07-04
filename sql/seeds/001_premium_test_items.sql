-- ============================================================
-- PREMIUM TEST ITEMS
-- Rich demo auction items with generated catalog-ready images.
--
-- Run after:
--   sql/migrations/001_event_foundation.sql
--   sql/migrations/002_favorites.sql
-- ============================================================

USE silentbidpro;

INSERT INTO organizations (
    name,
    slug,
    contact_email,
    contact_phone,
    brand_primary,
    brand_accent
) VALUES (
    'Silent Bid Pro Demo Foundation',
    'silent-bid-pro-demo-foundation',
    'hello@silentbidpro.local',
    '(555) 010-2026',
    '#245C4F',
    '#F2B84B'
) ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    contact_email = VALUES(contact_email),
    contact_phone = VALUES(contact_phone),
    brand_primary = VALUES(brand_primary),
    brand_accent = VALUES(brand_accent);

SET @organization_id := (
    SELECT id
    FROM organizations
    WHERE slug = 'silent-bid-pro-demo-foundation'
    LIMIT 1
);

INSERT INTO events (
    organization_id,
    name,
    slug,
    event_date,
    auction_start_time,
    auction_end_time,
    timezone,
    payment_mode,
    status
) VALUES (
    @organization_id,
    'Spring Giving Gala',
    'spring-giving-gala',
    DATE_ADD(CURDATE(), INTERVAL 14 DAY),
    NOW(),
    DATE_ADD(NOW(), INTERVAL 14 DAY),
    'America/Los_Angeles',
    'both',
    'open'
) ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    event_date = VALUES(event_date),
    auction_start_time = VALUES(auction_start_time),
    auction_end_time = VALUES(auction_end_time),
    timezone = VALUES(timezone),
    payment_mode = VALUES(payment_mode),
    status = VALUES(status);

SET @event_id := (
    SELECT id
    FROM events
    WHERE organization_id = @organization_id
      AND slug = 'spring-giving-gala'
    LIMIT 1
);

INSERT INTO categories (event_id, name, sort_order) VALUES
    (@event_id, 'Travel & Experiences', 10),
    (@event_id, 'Dining & Entertaining', 20),
    (@event_id, 'Home & Art', 30),
    (@event_id, 'Wellness', 40)
ON DUPLICATE KEY UPDATE
    sort_order = VALUES(sort_order);

SET @travel_category_id := (
    SELECT id FROM categories
    WHERE event_id = @event_id AND name = 'Travel & Experiences'
    LIMIT 1
);

SET @dining_category_id := (
    SELECT id FROM categories
    WHERE event_id = @event_id AND name = 'Dining & Entertaining'
    LIMIT 1
);

SET @home_art_category_id := (
    SELECT id FROM categories
    WHERE event_id = @event_id AND name = 'Home & Art'
    LIMIT 1
);

SET @wellness_category_id := (
    SELECT id FROM categories
    WHERE event_id = @event_id AND name = 'Wellness'
    LIMIT 1
);

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
(
    @event_id,
    @travel_category_id,
    201,
    'Napa Vineyard Weekend for Two',
    CONCAT(
        'Settle into a golden California weekend designed for slow mornings, beautiful views, and an easy toast to generosity. This two-night vineyard escape includes a boutique inn stay for two, a reserved tasting experience, and a welcome bottle selected by the host winery.',
        '\n\n',
        'Your days can be as relaxed or full as you like: wander sunlit rows, enjoy a terrace breakfast, visit nearby tasting rooms, and return to a comfortable room made for unwinding. It is an elegant auction favorite because it feels special without being fussy.',
        '\n\n',
        'Includes two-night lodging, curated tasting for two, welcome bottle, and concierge-style booking support. Dates subject to mutual availability; blackout dates may apply.'
    ),
    'images/items/generated/napa-vineyard-weekend.png',
    1800.00,
    550.00,
    50.00,
    2500.00,
    0.00,
    NOW(),
    DATE_ADD(NOW(), INTERVAL 14 DAY),
    NULL,
    0
),
(
    @event_id,
    @dining_category_id,
    202,
    'Private Chef Dinner Party for Eight',
    CONCAT(
        'Bring restaurant-level hospitality home with a private chef dinner crafted for eight guests. The evening begins with a seasonal menu consultation, then unfolds around beautifully plated courses, polished service, and a table that feels celebratory from the first pour to the final bite.',
        '\n\n',
        'This is a perfect package for birthdays, donor thank-yous, neighborhood gatherings, or one memorable night with friends. The winning bidder chooses the mood: relaxed family-style abundance, refined tasting-menu pacing, or a warm dinner party somewhere in between.',
        '\n\n',
        'Includes menu planning, chef preparation, dinner service, and standard cleanup. Food cost allowance included up to package value; date and location subject to chef availability.'
    ),
    'images/items/generated/private-chef-dinner.png',
    2200.00,
    700.00,
    50.00,
    3200.00,
    0.00,
    NOW(),
    DATE_ADD(NOW(), INTERVAL 14 DAY),
    NULL,
    0
),
(
    @event_id,
    @travel_category_id,
    203,
    'Mountain Cabin Retreat Weekend',
    CONCAT(
        'Trade the rush of the week for fresh pine air, lake views, and fireside mornings at a modern mountain cabin retreat. This weekend stay is made for deep rest: coffee on the deck, quiet trails nearby, a glowing fire pit at dusk, and room to reconnect without an overpacked itinerary.',
        '\n\n',
        'The package offers the kind of experience bidders can immediately picture themselves enjoying. It feels luxurious, but in the warm, grounded way that makes people want to invite family or close friends along.',
        '\n\n',
        'Includes a two-night cabin stay for up to four guests and a locally sourced welcome basket. Dates subject to availability; cleaning fees included; travel not included.'
    ),
    'images/items/generated/mountain-cabin-retreat.png',
    1400.00,
    425.00,
    25.00,
    1950.00,
    0.00,
    NOW(),
    DATE_ADD(NOW(), INTERVAL 14 DAY),
    DATE_ADD(NOW(), INTERVAL 13 DAY),
    0
),
(
    @event_id,
    @home_art_category_id,
    204,
    'Handcrafted Ceramic Art Collection',
    CONCAT(
        'A graceful collection of handmade ceramic pieces created for everyday beauty: sculptural vases, serving bowls, and tactile tableware with soft neutral glazes. Each piece has the quiet presence of studio craft, making the set equally at home on a dining table, entry console, or open kitchen shelf.',
        '\n\n',
        'This package celebrates local artistry and gives bidders something lasting to take home from the event. The collection is cohesive without feeling matched, refined without becoming too precious, and useful enough to become part of daily rituals.',
        '\n\n',
        'Includes assorted ceramic pieces selected by the artist. Exact forms and glaze variations may differ slightly because each piece is individually made.'
    ),
    'images/items/generated/ceramic-art-collection.png',
    650.00,
    200.00,
    25.00,
    950.00,
    0.00,
    NOW(),
    DATE_ADD(NOW(), INTERVAL 14 DAY),
    NULL,
    0
),
(
    @event_id,
    @wellness_category_id,
    205,
    'Luxury Wellness & Spa Day',
    CONCAT(
        'Give someone permission to pause with a spa day designed around calm, restoration, and unhurried care. The winning bidder receives a premium wellness experience featuring massage, botanical aromatherapy, robe-and-towel service, and access to peaceful spa amenities.',
        '\n\n',
        'This is an easy crowd-pleaser because it is personal, practical, and indulgent in the best possible way. It also makes a thoughtful gift for a parent, caregiver, teacher, volunteer, or anyone who spends a lot of time taking care of others.',
        '\n\n',
        'Includes one premium spa package and a wellness gift set. Services must be booked in advance and are subject to provider availability.'
    ),
    'images/items/generated/wellness-spa-day.png',
    520.00,
    175.00,
    25.00,
    750.00,
    0.00,
    NOW(),
    DATE_ADD(NOW(), INTERVAL 14 DAY),
    NULL,
    0
),
(
    @event_id,
    @travel_category_id,
    206,
    'VIP Theater Night Package',
    CONCAT(
        'Enjoy a polished night out with VIP theater seating, a pre-show toast, and the rare pleasure of having the evening already arranged. This package pairs two excellent seats with a hospitality credit, making it ideal for date night, visiting family, or a special celebration.',
        '\n\n',
        'The experience feels glamorous without becoming complicated: arrive, settle in, enjoy the lights, and let the performance make the night memorable. It is the kind of auction item that photographs beautifully, displays well in a catalog, and sparks instant conversation at a table.',
        '\n\n',
        'Includes two premium tickets to a mutually agreed performance and a hospitality credit for drinks or dessert. Performance dates and seating are subject to availability.'
    ),
    'images/items/generated/vip-theater-night.png',
    950.00,
    300.00,
    25.00,
    1350.00,
    0.00,
    NOW(),
    DATE_ADD(NOW(), INTERVAL 14 DAY),
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
