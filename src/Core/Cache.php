<?php
declare(strict_types=1);

namespace Bulbula\Core;

/**
 * Shared-Hosting Smart File Cache with Instant Generation-Tag Invalidation.
 *
 * Solves two shared-hosting problems simultaneously:
 * 1. Protects limited MySQL connection/CPU limits by serving heavy discovery reads
 *    (Homepage, Categories, Areas, Branch profiles, Autocomplete) from fast disk JSON.
 * 2. NEVER gets stuck or requires a server restart:
 *    - Uses a lightweight `_manifest.json` storing `global_epoch` + per-tag version counters.
 *    - Calling `Cache::invalidateTags(['branches', 'categories'])` or `Cache::flushAll()`
 *      immediately increments the version counter in `_manifest.json`.
 *    - Any cached file with an older tag version is automatically ignored and regenerated
 *      on the very next request—even if a shared host has sticky file permissions.
 */
final class Cache
{
    private static ?array $manifest = null;

    private static function cacheDir(): string
    {
        $dir = (defined('BULBULA_ROOT') ? BULBULA_ROOT : dirname(__DIR__, 2)) . '/storage/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private static function manifestPath(): string
    {
        return self::cacheDir() . '/_manifest.json';
    }

    private static function loadManifest(bool $forceReload = false): array
    {
        if (self::$manifest !== null && !$forceReload) {
            return self::$manifest;
        }

        $path = self::manifestPath();
        clearstatcache(true, $path);

        if (is_file($path)) {
            $raw = @file_get_contents($path);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($data) && isset($data['global_epoch'], $data['tags'])) {
                self::$manifest = $data;
                return self::$manifest;
            }
        }

        self::$manifest = [
            'global_epoch' => 1,
            'updated_at'   => time(),
            'tags'         => [],
        ];
        return self::$manifest;
    }

    private static function saveManifest(array $manifest): void
    {
        self::$manifest = $manifest;
        $path = self::manifestPath();
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, json_encode($manifest, JSON_PRETTY_PRINT), LOCK_EX) !== false) {
            @rename($tmp, $path);
            clearstatcache(true, $path);
        }
    }

    /**
     * Read from cache if valid, otherwise execute $callback, store atomically, and return.
     *
     * @param string   $key        Unique cache key (e.g. "home:discovery:bole-bulbula")
     * @param int      $ttlSeconds Time-to-live in seconds
     * @param string[] $tags       Invalidation tags (e.g. ['branches', 'campaigns'])
     * @param callable $callback   Producer function returning serializable data
     */
    public static function remember(string $key, int $ttlSeconds, array $tags, callable $callback): mixed
    {
        if (!(bool) Config::get('cache.enabled', true) || $ttlSeconds <= 0) {
            return $callback();
        }

        $hit = self::get($key, $tags);
        if ($hit['found']) {
            return $hit['value'];
        }

        $value = $callback();
        self::put($key, $value, $ttlSeconds, $tags);
        return $value;
    }

    /**
     * Retrieve cached entry only if TTL, global_epoch, and all tag epochs match.
     *
     * @return array{found: bool, value: mixed}
     */
    public static function get(string $key, array $tags = []): array
    {
        $file = self::keyToPath($key);
        clearstatcache(true, $file);

        if (!is_file($file)) {
            return ['found' => false, 'value' => null];
        }

        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            return ['found' => false, 'value' => null];
        }

        $envelope = json_decode($raw, true);
        if (!is_array($envelope) || !isset($envelope['expires_at'], $envelope['global_epoch'], $envelope['tag_epochs'])) {
            return ['found' => false, 'value' => null];
        }

        // 1. Check TTL expiration
        if (time() > (int) $envelope['expires_at']) {
            @unlink($file);
            return ['found' => false, 'value' => null];
        }

        // 2. Check global epoch (instant flush-all check)
        $manifest = self::loadManifest();
        if ((int) $envelope['global_epoch'] !== (int) $manifest['global_epoch']) {
            @unlink($file);
            return ['found' => false, 'value' => null];
        }

        // 3. Check every attached tag version
        $checkTags = array_unique(array_merge($tags, array_keys($envelope['tag_epochs'])));
        foreach ($checkTags as $tag) {
            $currentTagVer = (int) ($manifest['tags'][$tag] ?? 1);
            $cachedTagVer  = (int) ($envelope['tag_epochs'][$tag] ?? 0);
            if ($cachedTagVer !== $currentTagVer) {
                @unlink($file);
                return ['found' => false, 'value' => null];
            }
        }

        return ['found' => true, 'value' => $envelope['payload'] ?? null];
    }

    /**
     * Atomically store a value in the file cache with current tag epochs.
     */
    public static function put(string $key, mixed $value, int $ttlSeconds, array $tags = []): void
    {
        $manifest = self::loadManifest();
        $tagEpochs = [];
        foreach (array_unique($tags) as $tag) {
            $tagEpochs[$tag] = (int) ($manifest['tags'][$tag] ?? 1);
        }

        $envelope = [
            'key'          => $key,
            'created_at'   => time(),
            'expires_at'   => time() + $ttlSeconds,
            'global_epoch' => (int) $manifest['global_epoch'],
            'tag_epochs'   => $tagEpochs,
            'payload'      => $value,
        ];

        $file = self::keyToPath($key);
        $tmp  = $file . '.tmp.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, json_encode($envelope, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false) {
            @rename($tmp, $file);
        }
    }

    /**
     * Instantly invalidate one or more cache tags (called automatically on Staff writes).
     */
    public static function invalidateTags(array $tags): void
    {
        $manifest = self::loadManifest(true);
        foreach ($tags as $tag) {
            $manifest['tags'][$tag] = ((int) ($manifest['tags'][$tag] ?? 1)) + 1;
        }
        $manifest['updated_at'] = time();
        self::saveManifest($manifest);
    }

    /**
     * Instantly invalidate ALL cached payloads and remove `.cache.json` files.
     */
    public static function flushAll(): array
    {
        $manifest = self::loadManifest(true);
        $manifest['global_epoch'] = ((int) $manifest['global_epoch']) + 1;
        $manifest['updated_at']   = time();
        self::saveManifest($manifest);

        $deleted = 0;
        $files = glob(self::cacheDir() . '/*.cache.json') ?: [];
        foreach ($files as $file) {
            if (@unlink($file)) {
                $deleted++;
            }
        }

        return [
            'new_global_epoch' => $manifest['global_epoch'],
            'files_removed'    => $deleted,
        ];
    }

    private static function keyToPath(string $key): string
    {
        $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '_', substr($key, 0, 36));
        return self::cacheDir() . '/' . $safePrefix . '_' . hash('sha256', $key) . '.cache.json';
    }
}
