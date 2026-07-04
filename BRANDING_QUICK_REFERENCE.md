# CSS Branding System — Quick Reference

## One-Minute Overview

Silent Bid Pro now has a **comprehensive CSS branding system** that dynamically colors the entire UI based on event configuration. No hardcoding colors. No JavaScript required.

## What Changed?

| Before | After |
|--------|-------|
| Colors hardcoded in CSS | Colors in CSS variables |
| Same colors for all events | Different colors per event |
| Manual color updates | Admin panel updates |
| Limited customization | Full event customization |

## How It Works in 3 Steps

1. **Admin sets colors** via `/admin/event-branding.php`
2. **Page loads** and outputs event-specific CSS variables
3. **All UI elements automatically update** — buttons, cards, badges, etc.

## Files Created/Updated

```
NEW FILES:
  /css/branding-variables.css          (11 KB)  - CSS variables master file
  /lib/branding.php                    (12 KB)  - PHP helper functions
  /BRANDING_SYSTEM.md                  (16 KB)  - Complete documentation
  /BRANDING_IMPLEMENTATION_GUIDE.md    (15 KB)  - Implementation guide
  /BRANDING_EXAMPLES.php               (13 KB)  - Code examples
  /BRANDING_QUICK_REFERENCE.md         (this)   - Quick reference

UPDATED FILES:
  /includes/page-meta.php              - Added branding-variables.css
  /includes/branding-helper.php        - Enhanced to use new system
```

## Key CSS Variables

```css
--branding-primary          /* Main brand color (default: #2E7D32) */
--branding-accent           /* Action buttons (default: #F57C00) */
--branding-secondary        /* Alternative actions (default: #1976D2) */
--branding-text             /* Text color (default: #212121) */
--branding-background       /* Page background (default: #FFFFFF) */
--branding-border           /* Borders (default: #DDDDDD) */
--branding-success          /* Success color (default: #28785F) */
--branding-error            /* Error color (default: #EF4444) */
```

See `BRANDING_SYSTEM.md` for complete variable reference.

## Usage in CSS

### Before (Old Way)

```css
.button {
    background: #315fcb;
    color: white;
}

.button:hover {
    background: #2a4aa7;
}
```

### After (New Way)

```css
.button {
    background: var(--branding-primary);
    color: var(--branding-text-on-primary);
}

.button:hover {
    background: var(--branding-primary-dark);
}
```

## PHP Functions

```php
require_once 'lib/branding.php';

// Get all branding colors for an event
$branding = getBrandingData($event_id);
echo $branding['primary_color'];  // #2E7D32

// Generate CSS custom properties
$css = getBrandingCSS($event_id);
// Output: :root { --branding-primary: #2E7D32; ... }

// Output complete style tag
echo getBrandingStyleTag($event_id);

// Check color contrast (WCAG AA standard = 4.5:1)
if (hasGoodContrast('#FFFFFF', '#000000')) {
    echo "✓ Text is readable";
}

// Auto-select text color (black or white)
$text_color = getContrastingTextColor($bg_color);

// Validate hex color
if (isValidHexColor('#2E7D32')) {
    // Valid
}
```

## How Admin Changes Colors

1. Navigate to `/admin/event-branding.php`
2. Select organization and event
3. Update color picker or hex input
4. Click "Save Branding"
5. **All pages immediately reflect new colors** ✓

## Auto-Implementation

The system is **already integrated** into:
- ✓ All pages via `renderPageMeta()`
- ✓ Existing `includes/branding-helper.php`
- ✓ Page `<head>` via `renderBrandingStyleTag()`

## What You Need to Do

### Update Your CSS Files

Find any hardcoded colors and replace with variables:

```bash
# Find hardcoded colors
grep -r "background:\|color:" css/ | grep "#"
```

Then replace them:

```css
/* Find: background: #315fcb; */
/* Replace: background: var(--branding-primary); */

/* Find: background: #d99a2b; */
/* Replace: background: var(--branding-accent); */

/* Find: color: #172235; */
/* Replace: color: var(--branding-text); */
```

### CSS Conversion Cheat Sheet

| Old Color | Variable |
|-----------|----------|
| #315fcb (blue) | var(--branding-primary) |
| #d99a2b (gold) | var(--branding-accent) |
| #172235 (navy) | var(--branding-text) |
| #f4f7f2 (cream) | var(--branding-light-bg) |
| #ffffff (white) | var(--branding-background) |
| #e5ddcf (beige) | var(--branding-border) |
| #28785f (green) | var(--branding-success) |
| #ef4444 (red) | var(--branding-error) |

## Testing

1. **Visit any event page** in browser
2. **Open dev tools** (F12)
3. **Check Elements → <head> → <style>**
4. **Look for**: `data-branding="event-123"`
5. **Verify CSS variables** are set correctly
6. **Change admin branding colors**
7. **Refresh page** and confirm colors updated

## Common Issues & Solutions

| Issue | Solution |
|-------|----------|
| Colors not changing | Clear browser cache, verify `renderBrandingStyleTag()` called |
| Only some elements change | Some CSS still uses hardcoded colors, update them |
| Colors look wrong | Check contrast ratio with `hasGoodContrast()` |
| Variables not recognized | Ensure `branding-variables.css` is first stylesheet |

## Browser Support

| Browser | Support |
|---------|---------|
| Chrome | ✓ All versions |
| Firefox | ✓ All versions |
| Safari | ✓ 9.1+ |
| Edge | ✓ All versions |
| Mobile | ✓ All modern |
| IE 11 | ✓ Falls back to defaults |

## Default Colors (Ryan's Reach)

| Color | Hex | Usage |
|-------|-----|-------|
| Primary | #2E7D32 | Main brand, buttons |
| Accent | #F57C00 | CTAs, highlights |
| Secondary | #1976D2 | Alternative actions |
| Text | #212121 | Primary text |
| Background | #FFFFFF | Page background |
| Border | #DDDDDD | Borders |
| Success | #28785F | Success state |
| Error | #EF4444 | Error state |

## Next Steps

1. Read `BRANDING_SYSTEM.md` for detailed documentation
2. Review `BRANDING_IMPLEMENTATION_GUIDE.md` for implementation steps
3. Check `BRANDING_EXAMPLES.php` for code examples
4. Update CSS files to use `var(--branding-*)`
5. Test color changes in admin panel
6. Deploy to production

## File Locations

```
css/
  branding-variables.css          ← Master CSS variables
  main.css                         ← Update colors here
  admin.css                        ← Update colors here
  mobile.css                       ← Update colors here

lib/
  branding.php                     ← PHP helper functions

includes/
  branding-helper.php              ← Integration layer
  page-meta.php                    ← Updated stylesheet order

admin/
  event-branding.php               ← Admin UI for managing branding

api/
  event/branding.php               ← API endpoint for branding config
```

## Documentation

- **Complete Guide**: `/BRANDING_SYSTEM.md`
- **Implementation Steps**: `/BRANDING_IMPLEMENTATION_GUIDE.md`
- **Code Examples**: `/BRANDING_EXAMPLES.php`
- **This Quick Reference**: `/BRANDING_QUICK_REFERENCE.md`

## Key Benefits

✓ **No hardcoding** — Colors managed via variables
✓ **Per-event** — Different colors for each event
✓ **Dynamic** — Update colors without recompiling
✓ **Accessible** — Built-in WCAG contrast checking
✓ **Maintainable** — Centralized color management
✓ **Fast** — Native CSS variables, zero JavaScript
✓ **Compatible** — Works in all modern browsers

## Summary

The CSS branding system is **fully implemented** and ready to use. All you need to do is:

1. Update CSS files to use `var(--branding-*)`
2. Verify colors update when admin changes branding
3. Test on different events and devices

That's it! The infrastructure is in place and working.

---

For detailed information, see `/BRANDING_SYSTEM.md`
For implementation help, see `/BRANDING_IMPLEMENTATION_GUIDE.md`
For code examples, see `/BRANDING_EXAMPLES.php`
