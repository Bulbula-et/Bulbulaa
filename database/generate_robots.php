<?php
declare(strict_types=1);

/**
 * Deploy-time robots.txt artifact. Run after configuring app.url and after each
 * deployment/configured-origin change; static delivery avoids stale PHP OPcache
 * route snapshots on shared hosts. The dynamic route remains a fallback.
 */
define('BULBULA_ROOT', dirname(__DIR__));
require BULBULA_ROOT . '/src/Core/Autoloader.php';
\Bulbula\Core\Autoloader::register(BULBULA_ROOT . '/src');
\Bulbula\Core\Config::load(BULBULA_ROOT . '/config/env.json');

$url = rtrim((string) \Bulbula\Core\Config::get('app.url', ''), '/');
$parts = parse_url($url);
if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['https', 'http'], true)
    || empty($parts['host']) || isset($parts['query']) || isset($parts['fragment'])
    || isset($parts['path']) || preg_match('/[\r\n]/', $url)) {
    fwrite(STDERR, "app.url must be an absolute HTTP(S) origin without path, query, or fragment.\n");
    exit(1);
}

$target = BULBULA_ROOT . '/public_html/robots.txt';
$tmp = $target . '.tmp.' . bin2hex(random_bytes(6));
try {
    if (file_put_contents($tmp, \Bulbula\Controllers\Public\PolicyPageController::robotsText(), LOCK_EX) === false) {
        throw new RuntimeException('Failed to write robots.txt temporary file');
    }
    chmod($tmp, 0644);
    if (!rename($tmp, $target)) throw new RuntimeException('Failed to publish robots.txt');
    echo "Published origin-aware robots.txt for {$url}\n";
} finally {
    if (is_file($tmp)) unlink($tmp);
}
