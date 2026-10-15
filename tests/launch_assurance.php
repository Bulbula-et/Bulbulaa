<?php
declare(strict_types=1);

/**
 * Deterministic launch-assurance baseline.
 * This is not WCAG conformance testing and does not measure field Core Web Vitals.
 */
const MAX_HTML_BYTES = 750_000;
const MAX_RESPONSE_SECONDS = 2.0;

$base = rtrim(getenv('BULBULA_BASE_URL') ?: 'http://127.0.0.1:8080', '/');
$config = json_decode((string) file_get_contents(dirname(__DIR__) . '/config/env.json'), true, 512, JSON_THROW_ON_ERROR);
$publicOrigin = rtrim((string) ($config['app']['url'] ?? ''), '/');
$paths = ['/', '/search?q=coffee', '/categories', '/c/health-medical', '/areas', '/a/bulbula-93-mazoria', '/b/birhan-medium-clinic/bole-bulbula-main', '/about', '/policies/privacy'];
$indexedCanonicals = [];

function fail(string $message): never { fwrite(STDERR, "[FAIL] {$message}\n"); exit(1); }
function expect(bool $condition, string $message): void { if (!$condition) fail($message); }
function fetch(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false]);
    $body = curl_exec($ch);
    if (!is_string($body)) fail("Request failed: {$url}");
    $result = ['body' => $body, 'status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'time' => (float) curl_getinfo($ch, CURLINFO_TOTAL_TIME), 'type' => (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE)];
    curl_close($ch);
    return $result;
}

expect(filter_var($publicOrigin, FILTER_VALIDATE_URL) !== false, 'Configured app.url must be an absolute URL');
foreach ($paths as $path) {
    $response = fetch($base . $path);
    expect($response['status'] === 200, "{$path}: expected 200");
    expect(strlen($response['body']) <= MAX_HTML_BYTES, "{$path}: response exceeds " . MAX_HTML_BYTES . ' bytes');
    expect($response['time'] <= MAX_RESPONSE_SECONDS, "{$path}: response exceeded " . MAX_RESPONSE_SECONDS . ' seconds');

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    expect($dom->loadHTML($response['body']), "{$path}: invalid HTML document");
    $xpath = new DOMXPath($dom);
    expect(trim((string) $dom->documentElement?->getAttribute('lang')) !== '', "{$path}: html lang is missing");
    expect($xpath->query('//title[string-length(normalize-space(.)) > 0]')->length === 1, "{$path}: one non-empty title required");
    expect($xpath->query('//meta[@name="description" and string-length(normalize-space(@content)) > 0]')->length === 1, "{$path}: one meta description required");
    expect($xpath->query('//meta[@name="viewport"]')->length === 1, "{$path}: viewport metadata required");
    expect($xpath->query('//main')->length === 1, "{$path}: exactly one main landmark required");
    expect($xpath->query('//h1')->length === 1, "{$path}: exactly one h1 required");
    expect($xpath->query('//img[not(@alt)]')->length === 0, "{$path}: every image must declare alt");
    expect($xpath->query('//button[not(normalize-space(.)) and not(@aria-label)]')->length === 0, "{$path}: icon buttons need accessible names");

    $canonical = $xpath->query('//link[@rel="canonical"]')->item(0)?->attributes?->getNamedItem('href')?->nodeValue;
    expect(is_string($canonical) && str_starts_with($canonical, $publicOrigin . '/'), "{$path}: canonical must use configured absolute origin");
    expect(!str_contains($canonical, '?'), "{$path}: canonical must not contain a query string");
    // Policy wording is launch-gated and intentionally excluded from the sitemap.
    if (!str_starts_with($path, '/policies/')) $indexedCanonicals[] = $canonical;

    foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
        $data = json_decode((string) $script->textContent, true);
        expect(is_array($data), "{$path}: JSON-LD must decode");
        if (isset($data['url'])) expect(str_starts_with((string) $data['url'], $publicOrigin . '/'), "{$path}: JSON-LD URL must use configured origin");
    }
    printf("[PASS] %-52s %6d bytes %0.3fs\n", $path, strlen($response['body']), $response['time']);
}

$sitemap = fetch($base . '/sitemap.xml');
expect($sitemap['status'] === 200 && str_contains($sitemap['type'], 'application/xml'), 'Sitemap must return XML and 200');
$xml = simplexml_load_string($sitemap['body']);
expect($xml !== false && count($xml->url) > 0, 'Sitemap must be valid and non-empty XML');
$urls = [];
foreach ($xml->url as $entry) {
    $url = (string) $entry->loc;
    expect(str_starts_with($url, $publicOrigin . '/'), 'Every sitemap URL must use configured origin');
    $urls[] = $url;
}
expect(count($urls) === count(array_unique($urls)), 'Sitemap URLs must be unique');
foreach ($indexedCanonicals as $canonical) {
    expect(in_array($canonical, $urls, true), "Public indexed canonical absent from sitemap: {$canonical}");
}

$robots = fetch($base . '/robots.txt');
expect($robots['status'] === 200 && str_contains($robots['type'], 'text/plain'), 'robots.txt must return text/plain and 200');
expect(str_contains($robots['body'], "Sitemap: {$publicOrigin}/sitemap.xml"), 'robots.txt must advertise the configured sitemap URL');
expect(str_contains($robots['body'], 'Disallow: /staff'), 'robots.txt must exclude staff routes');

echo "\nLAUNCH ASSURANCE BASELINE PASSED. Automated checks are partial evidence only; manual accessibility and field-performance gates remain.\n";
