<?php
declare(strict_types=1);

namespace Bulbula\Core;

/**
 * Shared-Hosting Sliding-Window Rate Limiter.
 * Protects Email OTP (C-31), Staff login (D-45), and Public Reports (C-15) without Redis.
 */
final class RateLimiter
{
    public static function allow(string $bucketKey, int $maxAttempts, int $windowSeconds): bool
    {
        $cacheKey = 'ratelimit:' . $bucketKey;
        $entry    = Cache::get($cacheKey);
        $now      = time();

        $timestamps = [];
        if ($entry['found'] && is_array($entry['value'])) {
            foreach ($entry['value'] as $ts) {
                if (($now - (int) $ts) < $windowSeconds) {
                    $timestamps[] = (int) $ts;
                }
            }
        }

        if (count($timestamps) >= $maxAttempts) {
            return false;
        }

        $timestamps[] = $now;
        Cache::put($cacheKey, $timestamps, $windowSeconds);
        return true;
    }
}
