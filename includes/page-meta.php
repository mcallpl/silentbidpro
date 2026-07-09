<?php
// ============================================================
// PAGE METADATA
// Shared favicon, social preview, and canonical URL tags.
// ============================================================

function absoluteAssetUrl($path) {
    $path = ltrim((string)$path, '/');
    $base = defined('PUBLIC_SITE_URL') ? PUBLIC_SITE_URL : APP_DOMAIN;
    return rtrim($base, '/') . '/' . $path;
}

function currentCanonicalUrl() {
    $base = defined('PUBLIC_SITE_URL') ? PUBLIC_SITE_URL : APP_DOMAIN;
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $query = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
    return rtrim($base, '/') . $path . ($query ? '?' . $query : '');
}

function assetUrlWithVersion($path) {
    $path = (string)$path;
    if (preg_match('/^https?:\/\//', $path) || strpos($path, '?') !== false) {
        return $path;
    }

    $file_path = __DIR__ . '/../' . ltrim($path, '/');
    if (is_file($file_path)) {
        return $path . '?v=' . filemtime($file_path);
    }

    return $path;
}

function renderPageMeta($options = []) {
    $title = $options['title'] ?? APP_NAME;
    $description = $options['description'] ?? 'A polished silent auction experience for generous bidders, trusted nonprofits, and joyful fundraising events.';
    $canonical = $options['canonical'] ?? currentCanonicalUrl();
    $image = $options['image'] ?? absoluteAssetUrl('images/brand/silent-bid-pro-social-1200x630.png');
    $type = $options['type'] ?? 'website';
    $stylesheets = $options['stylesheets'] ?? ['css/branding-variables.css', 'css/main.css', 'css/branding.css', 'css/mobile.css'];
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($description); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonical); ?>">

    <meta property="og:site_name" content="<?php echo htmlspecialchars(APP_NAME); ?>">
    <meta property="og:type" content="<?php echo htmlspecialchars($type); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($description); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonical); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($image); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Silent Bid Pro fundraising auction preview">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($title); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($description); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($image); ?>">

    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="images/brand/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="images/brand/favicon-32.png">
    <link rel="apple-touch-icon" href="images/brand/apple-touch-icon.png">
    <link rel="manifest" href="site.webmanifest">
    <meta name="theme-color" content="#172235">
    <?php // iOS Smart App Banner — Safari shows a native install/open strip on
          // every page once the App Store listing resolves (Silent Bid Pro,
          // App Store id 6787838881). Harmlessly inert everywhere else. ?>
    <meta name="apple-itunes-app" content="app-id=6787838881">
    <?php foreach ($stylesheets as $stylesheet): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrlWithVersion($stylesheet)); ?>">
    <?php endforeach; ?>
    <?php
    // Inject branding CSS variables if available
    require_once __DIR__ . '/branding-helper.php';
    renderBrandingStyleTag();
    ?>
    <?php
}
?>
