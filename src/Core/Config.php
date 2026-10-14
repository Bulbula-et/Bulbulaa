<?php
declare(strict_types=1);

namespace Bulbula\Core;

/**
 * Live JSON Configuration Reader.
 * Why JSON instead of a .php array?
 * On shared hosting, PHP OPcache frequently caches .php config files in shared memory
 * and refuses to pick up edits without a PHP-FPM restart. Reading env.json with
 * clearstatcache() guarantees zero-restart live configuration updates.
 */
final class Config
{
    private static array $items = [];
    private static string $configPath = '';

    public static function load(string $jsonFilePath): void
    {
        self::$configPath = $jsonFilePath;
        clearstatcache(true, $jsonFilePath);

        if (!is_file($jsonFilePath)) {
            self::$items = [];
            return;
        }

        $raw = file_get_contents($jsonFilePath);
        if ($raw === false) {
            self::$items = [];
            return;
        }

        $decoded = json_decode($raw, true);
        self::$items = is_array($decoded) ? $decoded : [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $current = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    public static function setRuntime(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $current = &self::$items;

        foreach ($segments as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }

        $current = $value;
    }
}
