<?php
declare(strict_types=1);

/**
 * Built-in PHP Dev Server Router (`php -S 0.0.0.0:8080 -t public_html public_html/router.php`).
 * Mirrors `.htaccess` behavior for live preview.
 */
$uriPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (is_string($uriPath) && $uriPath !== '/' && is_file(__DIR__ . $uriPath)) {
    return false; // Serve static asset directly
}
require __DIR__ . '/index.php';
