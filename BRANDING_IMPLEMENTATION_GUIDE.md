# CSS Branding System — Implementation Guide

## Quick Start (5 minutes)

### 1. Include the stylesheet
Ensure `css/branding-variables.css` is included FIRST in all pages:

```php
<?php
renderPageMeta([
    'stylesheets' => [
        'css/branding-variables.css',  // MUST be first!
        'css/main.css',
        'css/mobile.css'
    ]
]);
?>
```

### 2. Output branding CSS for current event
In your page `<head>`, the branding helper automatically outputs event-specific colors:

```php
<?php
// In includes/page-meta.php (already done!)
renderBrandingStyleTag();
?>
```

### 3. Use CSS variables in stylesheets
Update your CSS files to use variables instead of hardcoded colors:

```css
/* Before */
.button { background: #2E7D32; }

/* After */
.button { background: var(--branding-primary); }
```

### 4. That's it!
Colors now automatically update when admin changes event branding in `/admin/event-branding.php`

---

## Files Created

### 1. `/css/branding-variables.css` (11 KB)
**Purpose**: Master CSS variables file with all branding colors and defaults

**Contains**:
- Primary, secondary, accent colors
- Text colors (normal, secondary, muted)
- Status colors (success, error, warning, info)
- Borders, shadows, transitions
- Utility classes for quick styling
- Accessibility features (high contrast, reduced motion)

**Usage**: Include in all pages as first stylesheet

### 2. `/lib/branding.php` (12 KB)
**Purpose**: Server-side PHP functions for branding management

**Key Functions**:
- `getBrandingData($event_id)` — Fetch complete branding config
- `getBrandingCSS($event_id)` — Generate CSS custom properties
- `getBrandingStyleTag($event_id)` — Output complete <style> tag
- `getBrandingJSON($event_id)` — JSON for JavaScript
- `isValidHexColor($color)` — Validate hex color codes
- `getContrastRatio($color1, $color2)` — Calculate WCAG contrast
- `hasGoodContrast($text, $bg)` — Check WCAG AA compliance
- `getContrastingTextColor($bg)` — Auto-select black or white text

**Usage**: Require in pages that need branding functions

### 3. Updated `/includes/branding-helper.php`
**Changes**:
- Now requires `/lib/branding.php`
- `renderBrandingStyleTag()` uses new comprehensive system
- Outputs complete event-specific CSS variables

**Backward compatible** — existing calls still work

### 4. Updated `/includes/page-meta.php`
**Changes**:
- Added `css/branding-variables.css` as first stylesheet
- Ensures CSS variables available on all pages
- No functional changes to existing code

---

## How It Works

```
Admin changes colors in /admin/event-branding.php
         ↓
Colors saved to events table
         ↓
Page loads, calls renderBrandingStyleTag()
         ↓
getBrandingCSS() fetches colors from database
         ↓
Generates :root { --branding-primary: #value; ... }
         ↓
Outputs in <style> tag in page <head>
         ↓
CSS files use var(--branding-primary)
         ↓
UI automatically shows new colors
```

---

## CSS Variables Available

### Primary Branding

```css
--branding-primary          /* Main brand color */
--branding-primary-dark     /* Hover/active state */
--branding-primary-light    /* Light backgrounds */

--branding-secondary        /* Secondary action color */
--branding-secondary-dark
--branding-secondary-light

--branding-accent           /* Call-to-action color */
--branding-accent-dark
--branding-accent-light
```

### Background & Text

```css
--branding-background       /* Page background */
--branding-light-bg         /* Section backgrounds */
--branding-text             /* Primary text */
--branding-text-secondary   /* Description text */
--branding-text-muted       /* Disabled/hint text */
--branding-text-on-primary  /* Text on colored backgrounds */
--branding-text-on-accent
```

### Status Colors

```css
--branding-success          /* Green for success */
--branding-error            /* Red for errors */
--branding-warning          /* Orange for warnings */
--branding-info             /* Blue for info */
```

### Effects & Spacing

```css
--branding-border           /* Border color */
--branding-shadow-sm        /* Subtle shadow */
--branding-shadow-md        /* Medium shadow */
--branding-shadow-lg        /* Large shadow */

--branding-radius-sm        /* Small border radius */
--branding-radius-md        /* Standard border radius */
--branding-radius-lg        /* Large border radius */

--branding-transition       /* Standard transition time */
--branding-transition-fast  /* Quick transition */
--branding-transition-slow  /* Slow transition */
```

---

## Usage Examples

### Example 1: Button Styling

```css
.btn-primary {
    background: var(--branding-primary);
    color: var(--branding-text-on-primary);
    border: none;
    padding: 10px 20px;
    border-radius: var(--branding-radius-md);
    transition: var(--branding-transition);
}

.btn-primary:hover {
    background: var(--branding-primary-dark);
}
```

### Example 2: Card Layout

```css
.card {
    background: var(--branding-background);
    border: 1px solid var(--branding-border);
    border-radius: var(--branding-radius-lg);
    box-shadow: var(--branding-shadow-md);
    padding: 20px;
}

.card-title {
    color: var(--branding-text);
}

.card-description {
    color: var(--branding-text-secondary);
}
```

### Example 3: Status Message

```css
.alert-success {
    background: var(--branding-light-bg);
    border-left: 4px solid var(--branding-success);
    color: var(--branding-success);
    padding: 15px;
}

.alert-error {
    background: var(--branding-light-bg);
    border-left: 4px solid var(--branding-error);
    color: var(--branding-error);
    padding: 15px;
}
```

### Example 4: Header

```css
.header {
    background: var(--branding-primary);
    color: var(--branding-text-on-primary);
    padding: 20px;
}

.header h1 {
    color: var(--branding-text-on-primary);
}

.header .accent {
    color: var(--branding-accent);
}
```

### Example 5: Input Fields

```css
input, textarea {
    border: 1px solid var(--branding-border);
    border-radius: var(--branding-radius-md);
    padding: 10px;
    color: var(--branding-text);
}

input:focus, textarea:focus {
    outline: none;
    border-color: var(--branding-primary);
    box-shadow: 0 0 0 3px rgba(var(--branding-primary-rgb), 0.1);
}
```

---

## PHP Functions Reference

### getBrandingData($event_id)

Fetch complete branding configuration.

```php
$branding = getBrandingData(123);

// Returns array:
[
    'primary_color' => '#2E7D32',
    'secondary_color' => '#1976D2',
    'accent_color' => '#F57C00',
    'background_color' => '#FFFFFF',
    'text_color' => '#212121',
    'text_secondary_color' => '#666666',
    'text_muted_color' => '#999999',
    'border_color' => '#DDDDDD',
    'light_bg_color' => '#F5F5F5',
    'success_color' => '#28785F',
    'error_color' => '#EF4444',
    'warning_color' => '#F57C00',
    'info_color' => '#1976D2',
    'organization_name' => 'Organization',
    'organization_logo_url' => null
]
```

### getBrandingCSS($event_id, $minify = true)

Generate CSS custom property overrides.

```php
// Minified (single line)
$css = getBrandingCSS(123);
// Output: :root { --branding-primary: #2E7D32; --branding-accent: #F57C00; ... }

// Pretty-printed (for debugging)
$css = getBrandingCSS(123, false);
// Output:
// :root {
//     --branding-primary: #2E7D32;
//     --branding-accent: #F57C00;
//     ...
// }
```

### getBrandingStyleTag($event_id)

Output complete `<style>` tag ready for embedding.

```php
echo getBrandingStyleTag(123);
// Output: <style data-branding="event-123">:root { ... }</style>
```

### getContrastRatio($color1, $color2)

Calculate WCAG contrast ratio between two colors.

```php
$ratio = getContrastRatio('#FFFFFF', '#212121');
// Returns: 17.5

// WCAG Standards:
// >= 7   = AAA (enhanced contrast)
// >= 4.5 = AA (standard compliance)
// < 4.5  = Fails accessibility
```

### hasGoodContrast($text_color, $bg_color, $min_ratio = 4.5)

Check if color combination meets WCAG AA standard.

```php
if (hasGoodContrast('#FFFFFF', '#212121')) {
    // Safe to use
    echo "✓ Text is readable";
} else {
    // Need to adjust colors
    echo "✗ Insufficient contrast";
}
```

### getContrastingTextColor($bg_color)

Automatically return black or white text based on background.

```php
$text_color = getContrastingTextColor('#F5F5F5');
// Returns: '#000000' (for light background)

$text_color = getContrastingTextColor('#000000');
// Returns: '#FFFFFF' (for dark background)
```

### isValidHexColor($color)

Validate hex color format.

```php
isValidHexColor('#2E7D32');     // true
isValidHexColor('#2E7');        // true (shorthand)
isValidHexColor('2E7D32');      // false (missing #)
isValidHexColor('not-a-color'); // false
```

---

## Integration Steps

### Step 1: Review Current CSS
Look at existing CSS files and identify all hardcoded colors:

```bash
grep -r "color:\|background:" css/ | grep "#"
```

### Step 2: Update CSS Gradually
Replace hardcoded colors with variables:

```css
/* Before */
.button { background: #315fcb; color: white; }
.button:hover { background: #2a4aa7; }

/* After */
.button { background: var(--branding-primary); color: var(--branding-text-on-primary); }
.button:hover { background: var(--branding-primary-dark); }
```

### Step 3: Test on Events
Verify colors update correctly:
1. Go to `/admin/event-branding.php`
2. Change event colors
3. View event pages
4. Confirm colors updated automatically

### Step 4: Verify Accessibility
Check color contrasts in admin panel:
1. Live preview shows contrast warnings
2. Use `hasGoodContrast()` in PHP for validation
3. Test with screen readers if applicable

### Step 5: Deploy
- Commit changes
- Deploy `css/branding-variables.css`
- Deploy `lib/branding.php`
- Updated files already merged into existing code

---

## Browser Compatibility

CSS custom properties (CSS variables) are supported in:
- ✓ Chrome 49+
- ✓ Firefox 31+
- ✓ Safari 9.1+
- ✓ Edge 15+
- ✓ Opera 36+
- ✓ Mobile browsers (iOS Safari 9.3+, Chrome Android)

**IE 11 and below**: Variables not supported, but pages still function with default colors from `branding-variables.css`

---

## Performance Tips

1. **CSS variables are cached** — minimal performance impact
2. **Minify output** — use `getBrandingCSS($id, true)` by default
3. **Cache database queries** — existing branding-helper caches per request
4. **Single style tag** — one `<style>` tag vs. multiple inline styles
5. **No JavaScript required** — pure CSS implementation

---

## Troubleshooting

### Issue: Colors not updating

**Check**:
1. Is `css/branding-variables.css` included FIRST?
2. Is `renderBrandingStyleTag()` called in `<head>`?
3. Are CSS files using `var(--branding-*)` syntax?
4. Clear browser cache
5. Check browser console for errors

### Issue: Only some elements change color

**Likely cause**: Some CSS files use hardcoded colors, not variables

**Solution**: Update those files to use variables:
```css
/* Find */
background: #315fcb;

/* Replace with */
background: var(--branding-primary);
```

### Issue: Colors look washed out on background

**Check**: Use `hasGoodContrast()` to verify contrast ratio

```php
if (!hasGoodContrast($text_color, $bg_color)) {
    // Colors don't meet WCAG AA standard (4.5:1)
    // Consider adjusting the chosen colors
}
```

### Issue: JavaScript not updating colors dynamically

**Ensure API is working**:
```bash
curl https://example.com/api/event/branding.php?id=1
```

**Check response** includes color fields, then update CSS:
```javascript
root.style.setProperty('--branding-primary', colors.primary_color);
```

---

## Database Schema

Branding data is stored in the `events` table:

```sql
ALTER TABLE events ADD COLUMN (
    primary_color VARCHAR(7) DEFAULT '#2E7D32',
    secondary_color VARCHAR(7) DEFAULT '#1976D2',
    accent_color VARCHAR(7) DEFAULT '#F57C00',
    background_color VARCHAR(7) DEFAULT '#FFFFFF',
    text_color VARCHAR(7) DEFAULT '#212121'
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Add indexes for performance
CREATE INDEX idx_event_colors ON events(primary_color, accent_color);
```

Optional: Create dedicated `event_branding` table for extended branding (logos, organization details):

```sql
CREATE TABLE event_branding (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL UNIQUE,
    primary_color VARCHAR(7) NOT NULL DEFAULT '#2E7D32',
    secondary_color VARCHAR(7) NOT NULL DEFAULT '#1976D2',
    accent_color VARCHAR(7) NOT NULL DEFAULT '#F57C00',
    background_color VARCHAR(7) NOT NULL DEFAULT '#FFFFFF',
    text_color VARCHAR(7) NOT NULL DEFAULT '#212121',
    organization_name VARCHAR(255),
    organization_logo_url VARCHAR(2000),
    event_location VARCHAR(255),
    event_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

## Color Defaults

Event branding defaults to "Ryan's Reach" colors if not configured:

| Color | Hex | Usage |
|-------|-----|-------|
| Primary | #2E7D32 | Main brand, buttons, headers |
| Secondary | #1976D2 | Alternative actions |
| Accent | #F57C00 | Call-to-action, emphasis |
| Background | #FFFFFF | Page background |
| Text | #212121 | Primary text |

These are applied automatically by `getBrandingData()` when colors aren't set.

---

## Testing

### Manual Testing

1. Visit any event page
2. Check dev tools (F12) → Elements → <head> → <style>
3. Verify `--branding-primary` and other variables are set
4. Change admin branding colors
5. Refresh page and verify colors updated

### Automated Testing

```php
// Test getBrandingData
$branding = getBrandingData(1);
assert($branding['primary_color'] === '#2E7D32');

// Test CSS generation
$css = getBrandingCSS(1);
assert(strpos($css, '--branding-primary') !== false);

// Test color validation
assert(isValidHexColor('#2E7D32') === true);
assert(isValidHexColor('invalid') === false);

// Test contrast ratio
$ratio = getContrastRatio('#FFFFFF', '#000000');
assert($ratio >= 4.5);
```

---

## Support Resources

- **Main Documentation**: `/BRANDING_SYSTEM.md`
- **Examples**: `/BRANDING_EXAMPLES.php`
- **Admin Panel**: `/admin/event-branding.php`
- **API**: `/api/event/branding.php`
- **Functions**: `/lib/branding.php`

---

## Version History

- **v1.0** (June 24, 2026)
  - Initial comprehensive branding system
  - CSS variables for all colors
  - PHP helper functions
  - WCAG accessibility features
  - Admin integration
  - API endpoints

---

## License & Credits

Created for Silent Bid Pro fundraising platform.
All colors, styling, and branding defaults designed for optimal accessibility and user experience.

See BRANDING_SYSTEM.md for complete documentation.
