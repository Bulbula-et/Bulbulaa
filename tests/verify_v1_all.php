<?php
declare(strict_types=1);

/**
 * Bulbula.et V1 — Public/Staff Route and Data Integrity Verification.
 * Requires the application server on BULBULA_BASE_URL (default 127.0.0.1:8080).
 */

define('BULBULA_ROOT', dirname(__DIR__));
require_once BULBULA_ROOT . '/vendor/autoload.php';
require_once BULBULA_ROOT . '/src/Core/Autoloader.php';
\Bulbula\Core\Autoloader::register(BULBULA_ROOT . '/src');
\Bulbula\Core\Config::load(BULBULA_ROOT . '/config/env.json');

function check(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "  [FAIL] {$message}\n");
        exit(1);
    }
}

function httpGet(string $url): array {
    $ch = curl_init($url);
    check($ch !== false, 'Could not initialize cURL');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/json,application/xml;q=0.9'],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);
    check(is_string($body), "HTTP request failed for {$url}: {$error}");
    return ['status' => $status, 'type' => $type, 'body' => $body];
}

echo "=== 1. Zero-Restart Cache & Config Verification ===\n";
$flushStats = \Bulbula\Core\Cache::flushAll();
check(isset($flushStats['new_global_epoch']), 'Cache flush must return new_global_epoch');
echo "  [PASS] Cache::flushAll() -> Epoch #{$flushStats['new_global_epoch']}\n";

echo "=== 2. Database Integrity Verification ===\n";
$pdo = \Bulbula\Core\Database::pdo();
$branchCount = (int) $pdo->query("SELECT COUNT(*) FROM branches WHERE publication_status = 'PUBLISHED'")->fetchColumn();
$catCount    = (int) $pdo->query("SELECT COUNT(*) FROM categories WHERE is_active = 1")->fetchColumn();
$areaCount   = (int) $pdo->query("SELECT COUNT(*) FROM areas WHERE is_active = 1")->fetchColumn();
check($branchCount >= 7, 'Must have at least 7 published pilot branches');
check($catCount >= 10, 'Must have active categories and subcategories');
check($areaCount >= 4, 'Must have active Bole Bulbula areas');
echo "  [PASS] DB: {$branchCount} published branches, {$catCount} categories, {$areaCount} areas\n";

echo "=== 3. Live HTTP Route & API Verification ===\n";
$base = getenv('BULBULA_BASE_URL') ?: 'http://127.0.0.1:8080';
$routes = [
    '/', '/search', '/search?q=coffee', '/api/v1/autocomplete?q=birhan',
    '/categories', '/c/health-medical', '/areas', '/a/bulbula-93-mazoria',
    '/a/bulbula-93-mazoria/clinics-medical-centers', '/nearby?lat=8.9538&lng=38.7892&radius=3500',
    '/b/birhan-medium-clinic/bole-bulbula-main', '/b/adey-specialty-coffee/bulbula-roastery-lounge',
    '/b/kalehiwot-pharmacy/bulbula-mariam-branch', '/b/zemen-fresh-supermarket',
    '/b/zemen-fresh-supermarket/bulbula-main-mart', '/b/zemen-fresh-supermarket/93-mazoria-express',
    '/auth/login', '/staff/login', '/about', '/policies/editorial', '/policies/verification',
    '/policies/sponsored', '/policies/privacy', '/policies/terms', '/sitemap.xml',
    '/staff', '/staff/listings', '/staff/verification', '/staff/taxonomy', '/staff/locations',
    '/staff/moderation', '/staff/reports', '/staff/campaigns', '/staff/analytics',
    '/staff/audit-logs', '/staff/users', '/staff/settings',
];

foreach ($routes as $path) {
    $res = httpGet($base . $path);
    check($res['status'] === 200, "Expected HTTP 200 for {$path}; got {$res['status']}");
    check(strlen($res['body']) > 20, "Response body unexpectedly empty for {$path}");
    foreach (['Fatal error', 'Warning:', 'Parse error', 'Deprecated:', 'Uncaught Error'] as $token) {
        check(!str_contains($res['body'], $token), "PHP error token '{$token}' found on {$path}");
    }
    printf("  [PASS] 200 %-48s (%d bytes)\n", $path, strlen($res['body']));
}

$notFound = httpGet($base . '/this-route-must-not-exist-ci');
check($notFound['status'] === 404, 'Unknown route must return HTTP 404');
echo "  [PASS] 404 unknown route behavior\n";

echo "\nALL " . (count($routes) + 4) . " ROUTE/DATA/CACHE CHECKS PASSED WITH ZERO ERRORS.\n";
