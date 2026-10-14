<?php
declare(strict_types=1);

namespace Bulbula\Core;

/**
 * Atomic, dependency-free fixed/sliding-window limiter for shared hosting.
 * Each bucket is updated under an exclusive OS file lock, preventing concurrent
 * OTP/login requests from bypassing limits through read-modify-write races.
 */
final class RateLimiter
{
    public static function allow(string $bucketKey, int $maxAttempts, int $windowSeconds, ?int $now = null): bool
    {
        if ($bucketKey === '' || $maxAttempts < 1 || $windowSeconds < 1) return false;
        $now ??= time();
        $path = self::bucketPath($bucketKey);
        $dir  = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) return false;

        $handle = @fopen($path, 'c+b');
        if ($handle === false) return false; // Security control fails closed.

        try {
            if (!flock($handle, LOCK_EX)) return false;
            rewind($handle);
            $raw = stream_get_contents($handle);
            $stored = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            $timestamps = [];
            if (is_array($stored)) {
                foreach ($stored as $timestamp) {
                    $ts = (int) $timestamp;
                    if ($ts > 0 && $ts <= $now + 5 && ($now - $ts) < $windowSeconds) $timestamps[] = $ts;
                }
            }

            $allowed = count($timestamps) < $maxAttempts;
            if ($allowed) $timestamps[] = $now;

            rewind($handle);
            if (!ftruncate($handle, 0)) return false;
            $json = json_encode($timestamps, JSON_THROW_ON_ERROR);
            if (fwrite($handle, $json) === false || !fflush($handle)) return false;
            @chmod($path, 0600);
            return $allowed;
        } catch (\Throwable) {
            return false;
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** Test/operations helper: removes only the named hashed bucket. */
    public static function clear(string $bucketKey): void
    {
        $path = self::bucketPath($bucketKey);
        if (is_file($path)) @unlink($path);
    }

    private static function bucketPath(string $bucketKey): string
    {
        $root = defined('BULBULA_ROOT') ? BULBULA_ROOT : dirname(__DIR__, 2);
        $secret = (string) Config::get('app.secret_key', 'bulbula-rate-limit');
        $filename = hash_hmac('sha256', $bucketKey, $secret) . '.json';
        return $root . '/storage/rate_limits/' . $filename;
    }
}
