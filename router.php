<?php
// Router for PHP built-in server - removes .php extensions from URLs
// Usage: php -S localhost:8000 router.php

$requested_file = $_SERVER['REQUEST_URI'];
$requested_file = parse_url($requested_file, PHP_URL_PATH);

// Remove leading slash
$requested_file = ltrim($requested_file, '/');

// Don't route if requesting actual files or directories
if (file_exists($requested_file) || is_dir($requested_file)) {
    return false;
}

// Try with .php extension
if (file_exists($requested_file . '.php')) {
    include $requested_file . '.php';
    return true;
}

// Route to index.php for root
if (empty($requested_file) || $requested_file === '/') {
    include 'index.php';
    return true;
}

// File not found
return false;
