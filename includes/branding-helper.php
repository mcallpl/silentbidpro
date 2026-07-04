<?php
// ============================================================
// EVENT BRANDING HELPER
// Dynamically load and apply event branding colors and assets
// Caches branding data for performance
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';
require_once __DIR__ . '/events.php';
require_once __DIR__ . '/../lib/branding.php';

/**
 * Cache for branding data (in-memory during request)
 */
$_branding_cache = null;

/**
 * Get branding data for the current event
 * Cached for performance within a request
 * @return array|null Branding data with colors, logo, organization name, event details
 */
function getBrandingData() {
    global $_branding_cache;

    if ($_branding_cache !== null) {
        return $_branding_cache;
    }

    $event = getCurrentEvent();
    if (!$event) {
        $_branding_cache = false;
        return null;
    }

    // Fallback colors if not configured
    $brand_primary = !empty($event['brand_primary']) ? $event['brand_primary'] : '#315fcb';
    $brand_accent = !empty($event['brand_accent']) ? $event['brand_accent'] : '#d99a2b';
    $logo_url = !empty($event['logo_url']) ? $event['logo_url'] : null;

    $_branding_cache = [
        'event_id' => $event['id'],
        'event_name' => $event['name'] ?? 'Active Auction',
        'organization_name' => $event['organization_name'] ?? APP_NAME,
        'organization_id' => $event['organization_id'] ?? null,
        'brand_primary' => $brand_primary,
        'brand_accent' => $brand_accent,
        'logo_url' => $logo_url,
        'event_date' => $event['event_date'] ?? null,
        'auction_start_time' => $event['auction_start_time'] ?? null,
        'auction_end_time' => $event['auction_end_time'] ?? null,
        'timezone' => $event['timezone'] ?? 'UTC',
    ];

    return $_branding_cache;
}

/**
 * Get event location details if available
 * @return array|null Array with location data or null
 */
function getEventLocation() {
    $event = getCurrentEvent();
    if (!$event) {
        return null;
    }

    // Check if event has location columns
    if (!dbColumnExists('events', 'location_city')) {
        return null;
    }

    $location = dbGetRow(
        "SELECT location_city, location_state, location_country, location_address, location_venue_name
         FROM events WHERE id = ?",
        [(int)$event['id']]
    );

    return $location;
}

/**
 * Get event mission/description if available
 * @return string|null
 */
function getEventMission() {
    $event = getCurrentEvent();
    if (!$event) {
        return null;
    }

    // Check if event has mission columns
    if (!dbColumnExists('events', 'mission_statement')) {
        return null;
    }

    $result = dbGetRow(
        "SELECT mission_statement FROM events WHERE id = ?",
        [(int)$event['id']]
    );

    return $result['mission_statement'] ?? null;
}

/**
 * Render branding CSS variables for dynamic styling
 * Should be called in <head> or as inline style
 * @return string CSS string with :root variables
 */
function renderBrandingCSS() {
    $branding = getBrandingData();
    if (!$branding) {
        return '';
    }

    $css = sprintf(
        ':root { --event-brand-primary: %s; --event-brand-accent: %s; }',
        htmlspecialchars($branding['brand_primary']),
        htmlspecialchars($branding['brand_accent'])
    );

    return $css;
}

/**
 * Render a <style> tag with branding CSS variables
 * Include this in the head of pages that need dynamic branding
 *
 * Now uses comprehensive CSS variables from lib/branding.php
 * Supports all colors: primary, secondary, accent, states, borders, etc.
 */
function renderBrandingStyleTag() {
    // First try to get active event
    $event = getCurrentEvent();
    if (!$event) {
        // Use default CSS variables
        echo '<!-- No active event, using default branding -->' . "\n";
        return;
    }

    // Use new comprehensive branding system from lib/branding.php
    $css = getBrandingCSS($event['id'], true);
    if (!$css) {
        return '';
    }

    echo '<style data-branding="event-' . (int)$event['id'] . '">' . $css . '</style>' . "\n";
}

/**
 * Format event date/time for display
 * @param string $datetime MySQL datetime string
 * @param string $format Optional PHP date format
 * @return string Formatted date
 */
function formatEventDateTime($datetime, $format = 'M j, Y g:i A') {
    if (empty($datetime)) {
        return '';
    }

    try {
        return date($format, strtotime($datetime));
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * Format location for display
 * @param array|null $location Location data from getEventLocation()
 * @return string Formatted location
 */
function formatLocation($location) {
    if (!$location) {
        return '';
    }

    $parts = [];
    if (!empty($location['location_venue_name'])) {
        $parts[] = $location['location_venue_name'];
    }
    if (!empty($location['location_city'])) {
        $parts[] = $location['location_city'];
    }
    if (!empty($location['location_state'])) {
        $parts[] = $location['location_state'];
    }

    return implode(', ', array_filter($parts));
}

/**
 * Render event header banner with branding
 * Includes organization logo, event name, date, location, and mission
 * @param array $options Optional customization
 */
function renderEventBanner($options = []) {
    $branding = getBrandingData();
    if (!$branding) {
        return;
    }

    $location = getEventLocation();
    $mission = getEventMission();
    $show_logo = $options['show_logo'] ?? true;
    $show_mission = $options['show_mission'] ?? false;

    ?>
    <div class="event-banner" style="--event-primary: <?php echo htmlspecialchars($branding['brand_primary']); ?>; --event-accent: <?php echo htmlspecialchars($branding['brand_accent']); ?>;">
        <div class="event-banner-content">
            <?php if ($show_logo && $branding['logo_url']): ?>
                <div class="event-banner-logo">
                    <img src="<?php echo htmlspecialchars($branding['logo_url']); ?>"
                         alt="<?php echo htmlspecialchars($branding['organization_name']); ?> logo"
                         class="org-logo"
                    />
                </div>
            <?php endif; ?>

            <div class="event-banner-info">
                <p class="event-banner-org"><?php echo htmlspecialchars($branding['organization_name']); ?></p>
                <h1 class="event-banner-title"><?php echo htmlspecialchars($branding['event_name']); ?></h1>

                <?php if ($branding['event_date']): ?>
                    <p class="event-banner-date">
                        📅 <?php echo htmlspecialchars(formatEventDateTime($branding['event_date'], 'F j, Y')); ?>
                    </p>
                <?php endif; ?>

                <?php if ($location && formatLocation($location)): ?>
                    <p class="event-banner-location">
                        📍 <?php echo htmlspecialchars(formatLocation($location)); ?>
                    </p>
                <?php endif; ?>

                <?php if ($show_mission && $mission): ?>
                    <p class="event-banner-mission">
                        <?php echo htmlspecialchars($mission); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Get brand color for use in inline styles
 * @param string $type 'primary' or 'accent'
 * @return string Hex color code
 */
function getBrandColor($type = 'primary') {
    $branding = getBrandingData();
    if (!$branding) {
        return $type === 'primary' ? '#315fcb' : '#d99a2b';
    }

    return $type === 'primary' ? $branding['brand_primary'] : $branding['brand_accent'];
}

/**
 * Check if branding is configured for the event
 * @return bool
 */
function hasBranding() {
    return getBrandingData() !== null && getBrandingData() !== false;
}
?>
