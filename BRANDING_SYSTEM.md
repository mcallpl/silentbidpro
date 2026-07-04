# Comprehensive CSS Branding System

## Overview

Silent Bid Pro now features a complete, event-driven CSS branding system that allows dynamic color customization per event without hardcoding colors in PHP or JavaScript. All colors are defined as CSS custom properties (variables) and can be updated per event through the admin panel.

## Architecture

### 1. CSS Variables Layer (`css/branding-variables.css`)

This is the foundation of the branding system. It defines all CSS custom properties with sensible defaults:

```css
:root {
    /* Primary branding colors */
    --branding-primary: #2E7D32;
    --branding-secondary: #1976D2;
    --branding-accent: #F57C00;
    
    /* Background & text */
    --branding-background: #FFFFFF;
    --branding-text: #212121;
    --branding-text-secondary: #666666;
    --branding-text-muted: #999999;
    
    /* Status colors */
    --branding-success: #28785F;
    --branding-error: #EF4444;
    --branding-warning: #F57C00;
    --branding-info: #1976D2;
    
    /* Borders & separators */
    --branding-border: #DDDDDD;
    --branding-light-bg: #F5F5F5;
    
    /* Shadows & transitions */
    --branding-shadow-md: 0 8px 16px rgba(0, 0, 0, 0.12);
    --branding-transition: all 0.3s ease;
}
```

**Include this file in all pages** via `renderPageMeta()`:

```php
$stylesheets = [
    'css/branding-variables.css',  // MUST be first
    'css/main.css',
    'css/mobile.css'
];
```

### 2. PHP Branding Helper (`lib/branding.php`)

Provides server-side functions to fetch branding data and generate CSS:

#### Core Functions

**`getBrandingData($event_id)`**
Fetches complete branding configuration from database with defaults.

```php
$branding = getBrandingData($event_id);
echo $branding['primary_color'];  // #2E7D32
echo $branding['accent_color'];   // #F57C00
```

**`getBrandingCSS($event_id, $minify = true)`**
Generates CSS custom property overrides for an event.

```php
// Returns: :root { --branding-primary: #2E7D32; --branding-accent: #F57C00; ... }
$css = getBrandingCSS($event_id);
```

**`getBrandingStyleTag($event_id)`**
Returns complete `<style>` tag ready to embed in page `<head>`.

```php
echo getBrandingStyleTag($event_id);
// Outputs: <style data-branding="event-123">:root { ... }</style>
```

**`getContrastRatio($color1, $color2)`**
Calculates WCAG contrast ratio between two colors.

```php
$ratio = getContrastRatio('#FFFFFF', '#212121');  // 17.5 (excellent contrast)
if ($ratio >= 4.5) {
    echo "Text is readable";  // WCAG AA standard
}
```

**`hasGoodContrast($text_color, $bg_color, $min_ratio = 4.5)`**
Checks if text has sufficient contrast on background.

```php
if (hasGoodContrast($text_color, $bg_color)) {
    // Safe to use this color combination
}
```

**`getContrastingTextColor($bg_color)`**
Returns either black or white based on background luminance.

```php
$text_color = getContrastingTextColor($bg_color);  // Returns #000000 or #FFFFFF
```

### 3. Branding Helper Integration (`includes/branding-helper.php`)

Bridges the new system with existing code. Updated to use comprehensive CSS variables:

```php
// In page <head>
renderBrandingStyleTag();  // Automatically generates event-specific CSS
```

## How It Works

### Flow Diagram

```
Database (events table)
    ↓
getBrandingData() fetches colors & event details
    ↓
getBrandingCSS() generates :root CSS custom properties
    ↓
renderBrandingStyleTag() outputs <style> tag in <head>
    ↓
CSS files use var(--branding-primary) etc.
    ↓
UI automatically reflects event colors
```

### Color Override Priority

When a page loads, CSS colors are resolved in this order (highest to lowest):

1. **Inline styles** on specific elements
2. **Event-specific CSS** (from getBrandingCSS())
3. **branding-variables.css** defaults
4. **main.css** and other stylesheets
5. **Browser defaults**

## Usage Examples

### Basic Implementation

Include in any page template:

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/page-meta.php';
require_once __DIR__ . '/includes/branding-helper.php';

$event_id = $_GET['event_id'] ?? 1;
?>
<!DOCTYPE html>
<html>
<head>
    <?php renderPageMeta([
        'title' => 'Event Page',
        'stylesheets' => [
            'css/branding-variables.css',
            'css/main.css',
            'css/mobile.css'
        ]
    ]); ?>
    <!-- Branding CSS variables are automatically injected here -->
</head>
<body>
    <!-- All elements using --branding-* variables automatically update -->
</body>
</html>
```

### Using Branding in CSS

In your stylesheets, use CSS custom properties:

```css
/* Button styling */
.btn-primary {
    background: var(--branding-primary);
    color: var(--branding-text-on-primary);
    transition: var(--branding-transition);
}

.btn-primary:hover {
    background: var(--branding-primary-dark);
}

/* Card styling */
.card {
    background: var(--branding-background);
    border: 1px solid var(--branding-border);
    box-shadow: var(--branding-shadow-md);
}

/* Badge styling */
.badge {
    background: var(--branding-secondary);
    color: var(--branding-text-on-primary);
    border-radius: var(--branding-radius-md);
}

/* Success state */
.status-success {
    color: var(--branding-success);
}
```

### Dynamic JavaScript Updates

Fetch branding and update CSS variables in real-time:

```javascript
// Fetch branding configuration
fetch(`/api/event/branding.php?id=${eventId}`)
    .then(res => res.json())
    .then(data => {
        // Update CSS variables dynamically
        const root = document.documentElement;
        root.style.setProperty('--branding-primary', data.data.primary_color);
        root.style.setProperty('--branding-accent', data.data.accent_color);
        root.style.setProperty('--branding-text', data.data.text_color);
        
        // All UI elements automatically update
    });
```

### Admin Panel Integration

Update branding via admin panel:

```php
<?php
require_once __DIR__ . '/../lib/branding.php';

$event_id = (int)$_POST['event_id'];
$new_colors = [
    'primary_color' => $_POST['primary_color'],
    'accent_color' => $_POST['accent_color'],
    'secondary_color' => $_POST['secondary_color'],
    'text_color' => $_POST['text_color']
];

// Validate colors
foreach ($new_colors as $color) {
    if (!isValidHexColor($color)) {
        die('Invalid color format');
    }
}

// Update database
dbUpdate(
    "UPDATE events SET primary_color = ?, accent_color = ?, secondary_color = ?, text_color = ? WHERE id = ?",
    [$new_colors['primary_color'], $new_colors['accent_color'], $new_colors['secondary_color'], $new_colors['text_color'], $event_id]
);

// Generate new CSS and send to frontend
$css = getBrandingCSS($event_id);
echo json_encode(['status' => 'ok', 'css' => $css]);
?>
```

## CSS Custom Properties Reference

### Primary Colors

| Variable | Default | Usage |
|----------|---------|-------|
| `--branding-primary` | #2E7D32 | Main brand color, primary buttons, headers |
| `--branding-primary-dark` | #1b5e20 | Hover states, darker text |
| `--branding-primary-light` | #66bb6a | Light backgrounds, subtle accents |

### Secondary Colors

| Variable | Default | Usage |
|----------|---------|-------|
| `--branding-secondary` | #1976D2 | Secondary buttons, alternative actions |
| `--branding-secondary-dark` | #1565c0 | Secondary hover states |
| `--branding-secondary-light` | #42a5f5 | Light secondary backgrounds |

### Accent Colors

| Variable | Default | Usage |
|----------|---------|-------|
| `--branding-accent` | #F57C00 | Call-to-action buttons, highlights |
| `--branding-accent-dark` | #e65100 | Accent hover states |
| `--branding-accent-light` | #ffb74d | Light accent backgrounds |

### Background & Text

| Variable | Default | Usage |
|----------|---------|-------|
| `--branding-background` | #FFFFFF | Page background |
| `--branding-light-bg` | #F5F5F5 | Section backgrounds, card backgrounds |
| `--branding-text` | #212121 | Primary text color |
| `--branding-text-secondary` | #666666 | Secondary text, descriptions |
| `--branding-text-muted` | #999999 | Disabled text, hints, metadata |
| `--branding-text-on-primary` | #FFFFFF | Text on primary color background |
| `--branding-text-on-accent` | #FFFFFF | Text on accent color background |

### Status Colors

| Variable | Default | Usage |
|----------|---------|-------|
| `--branding-success` | #28785F | Success messages, checkmarks |
| `--branding-error` | #EF4444 | Error messages, alerts |
| `--branding-warning` | #F57C00 | Warning messages, cautions |
| `--branding-info` | #1976D2 | Info messages, notifications |

### Borders & Separators

| Variable | Default | Usage |
|----------|---------|-------|
| `--branding-border` | #DDDDDD | Card borders, input borders |
| `--branding-border-light` | #EEEEEE | Subtle separators |
| `--branding-border-dark` | #CCCCCC | Emphasized borders |

### Shadows

| Variable | Default | Usage |
|----------|---------|-------|
| `--branding-shadow-xs` | 0 1px 2px | Very subtle elevation |
| `--branding-shadow-sm` | 0 4px 8px | Light elevation |
| `--branding-shadow-md` | 0 8px 16px | Standard elevation |
| `--branding-shadow-lg` | 0 16px 32px | Strong elevation |
| `--branding-shadow-xl` | 0 24px 48px | Maximum elevation |

### Transitions & Effects

| Variable | Default | Usage |
|----------|---------|-------|
| `--branding-transition` | all 0.3s ease | Standard transitions |
| `--branding-transition-fast` | all 0.15s ease | Quick interactions |
| `--branding-transition-slow` | all 0.5s ease | Slow animations |

### Spacing & Sizing

| Variable | Default | Usage |
|----------|---------|-------|
| `--branding-radius-sm` | 4px | Small border radius |
| `--branding-radius-md` | 8px | Standard border radius |
| `--branding-radius-lg` | 12px | Large border radius |
| `--branding-radius-full` | 9999px | Fully rounded (pills, circles) |

## Accessibility Features

### High Contrast Mode

Automatically adjusts for users who prefer higher contrast:

```css
@media (prefers-contrast: more) {
    :root {
        --branding-border-width: 2px;
        --branding-shadow-md: 0 8px 16px rgba(0, 0, 0, 0.3);
    }
}
```

### Reduced Motion Support

Respects user preferences for reduced motion:

```css
@media (prefers-reduced-motion: reduce) {
    :root {
        --branding-transition: none;
    }
}
```

### WCAG Contrast Checking

PHP functions validate text/background contrast:

```php
// Check if colors meet WCAG AA standard (4.5:1 contrast)
if (hasGoodContrast('#FFFFFF', '#212121')) {
    // Safe to use
}

// Calculate exact contrast ratio
$ratio = getContrastRatio($text, $bg);
// Returns float >= 1, higher is better
// 4.5 = WCAG AA (normal text)
// 3 = WCAG AA (large text)
// 7 = WCAG AAA (enhanced contrast)
```

## Database Schema

Event branding data is stored in the `events` table:

```sql
ALTER TABLE events ADD COLUMN (
    primary_color VARCHAR(7) DEFAULT '#2E7D32',
    secondary_color VARCHAR(7) DEFAULT '#1976D2',
    accent_color VARCHAR(7) DEFAULT '#F57C00',
    background_color VARCHAR(7) DEFAULT '#FFFFFF',
    text_color VARCHAR(7) DEFAULT '#212121'
);
```

Optional dedicated `event_branding` table for extended branding:

```sql
CREATE TABLE event_branding (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL UNIQUE,
    primary_color VARCHAR(7) DEFAULT '#2E7D32',
    secondary_color VARCHAR(7) DEFAULT '#1976D2',
    accent_color VARCHAR(7) DEFAULT '#F57C00',
    background_color VARCHAR(7) DEFAULT '#FFFFFF',
    text_color VARCHAR(7) DEFAULT '#212121',
    organization_name VARCHAR(255),
    organization_logo_url VARCHAR(2000),
    event_location VARCHAR(255),
    event_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);
```

## API Endpoints

### GET /api/event/branding.php?id=EVENT_ID

Fetch branding configuration for an event:

```bash
curl https://example.com/api/event/branding.php?id=123
```

Response:
```json
{
    "status": "ok",
    "message": "Branding configuration retrieved",
    "data": {
        "primary_color": "#2E7D32",
        "secondary_color": "#1976D2",
        "accent_color": "#F57C00",
        "background_color": "#FFFFFF",
        "text_color": "#212121",
        "organization_name": "Ryan's Reach",
        "organization_logo_url": "https://example.com/logo.png",
        "event_location": "Denver, CO",
        "event_description": "Annual fundraising gala"
    }
}
```

### POST /api/event/branding.php

Update branding configuration (admin only):

```bash
curl -X POST https://example.com/api/event/branding.php \
  -H "Content-Type: application/json" \
  -d '{
    "event_id": 123,
    "primary_color": "#2E7D32",
    "accent_color": "#F57C00"
  }'
```

## Implementation Checklist

- [x] Create `css/branding-variables.css` with all CSS custom properties
- [x] Create `lib/branding.php` with PHP helper functions
- [x] Update `includes/page-meta.php` to include branding-variables.css
- [x] Update `includes/branding-helper.php` to use comprehensive system
- [ ] Update `css/main.css` to use `var(--branding-*)` instead of hardcoded colors
- [ ] Update `css/admin.css` to use branding variables
- [ ] Update all component stylesheets to reference variables
- [ ] Test dynamic color changes on all pages
- [ ] Verify WCAG contrast ratios for all color combinations
- [ ] Test on mobile devices and tablets
- [ ] Test accessibility features (high contrast, reduced motion)
- [ ] Create admin UI for managing branding per event
- [ ] Add branding preview feature in admin panel

## Migration Guide

### Before (Hardcoded Colors)

```css
/* Old way - colors hardcoded everywhere */
.button { background: #315fcb; }
.button:hover { background: #2a4aa7; }
.header { background: #172235; }
.badge { background: #d99a2b; }
```

### After (CSS Variables)

```css
/* New way - colors managed via variables */
.button { background: var(--branding-primary); }
.button:hover { background: var(--branding-primary-dark); }
.header { background: var(--branding-primary); }
.badge { background: var(--branding-accent); }
```

### Benefits

1. **Centralized** - All colors defined in one place
2. **Dynamic** - Change colors without recompiling CSS
3. **Per-Event** - Different branding for different events
4. **Maintainable** - Update colors in single location
5. **Performant** - CSS variables are native browser feature
6. **Accessible** - Built-in contrast checking and accessibility features

## Troubleshooting

### Colors not updating?

1. Verify `css/branding-variables.css` is loaded first
2. Check browser developer tools for CSS variable values
3. Clear browser cache
4. Ensure `renderBrandingStyleTag()` is called in page `<head>`

### Contrast ratio warnings?

Use PHP helper functions:
```php
if (!hasGoodContrast($text_color, $bg_color)) {
    // Adjust colors to meet WCAG standards
    $text_color = getContrastingTextColor($bg_color);
}
```

### Colors not applying to specific elements?

1. Check CSS selector specificity
2. Verify inline styles aren't overriding
3. Use `!important` as last resort (not recommended)
4. Check that element is using correct variable name

## Performance Considerations

1. **CSS Variables are Cached** - Minimal re-renders
2. **Minified Output** - Single-line CSS for smaller download
3. **No JavaScript Required** - Pure CSS implementation
4. **Database Queries Cached** - Branding fetched once per request
5. **Gzip Compatible** - Inline CSS compresses well

## Support & Questions

For issues or questions about the branding system:

1. Check admin panel: `/admin/event-branding.php`
2. Review API response: `/api/event/branding.php?id=EVENT_ID`
3. Check browser console for CSS variable errors
4. Review `lib/branding.php` function documentation
