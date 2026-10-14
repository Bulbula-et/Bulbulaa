<?php
declare(strict_types=1);

namespace Bulbula\Core;

/**
 * Zero-restart layered JSON/environment configuration for shared hosting.
 *
 * Precedence (lowest -> highest):
 *  1. tracked config/env.json (safe defaults, never production secrets)
 *  2. ignored config/env.local.json (chmod 0600; convenient for cPanel)
 *  3. server environment variables (preferred in production)
 */
final class Config
{
    private static array $items = [];
    private static string $configPath = '';

    private const ENV_MAP = [
        'BULBULA_APP_ENV'               => 'app.env',
        'BULBULA_APP_DEBUG'             => 'app.debug',
        'BULBULA_PREVIEW_FEATURES'       => 'app.preview_features',
        'BULBULA_APP_URL'               => 'app.url',
        'BULBULA_APP_SECRET'            => 'app.secret_key',
        'BULBULA_CACHE_REFRESH_TOKEN'   => 'app.cache_refresh_token',
        'BULBULA_DB_DRIVER'             => 'database.driver',
        'BULBULA_DB_HOST'               => 'database.host',
        'BULBULA_DB_PORT'               => 'database.port',
        'BULBULA_DB_NAME'               => 'database.database',
        'BULBULA_DB_USER'               => 'database.username',
        'BULBULA_DB_PASSWORD'           => 'database.password',
        'BULBULA_STAFF_LOGIN_REQUIRED'  => 'security.require_staff_login',
        'BULBULA_STAFF_SESSION_IDLE'    => 'security.staff_session_idle_seconds',
        'BULBULA_STAFF_SESSION_ABSOLUTE'=> 'security.staff_session_absolute_seconds',
        'BULBULA_CUSTOMER_SESSION_IDLE' => 'security.customer_session_idle_seconds',
        'BULBULA_CUSTOMER_SESSION_ABSOLUTE' => 'security.customer_session_absolute_seconds',
        'BULBULA_GOOGLE_CLIENT_ID'      => 'auth.google_client_id',
        'BULBULA_GOOGLE_CLIENT_SECRET'  => 'auth.google_client_secret',
        'BULBULA_TELEGRAM_BOT_USERNAME' => 'auth.telegram_bot_username',
        'BULBULA_TELEGRAM_BOT_TOKEN'    => 'auth.telegram_bot_token',
        'BULBULA_MAIL_DRIVER'            => 'mail.driver',
        'BULBULA_MAIL_HOST'              => 'mail.host',
        'BULBULA_MAIL_PORT'              => 'mail.port',
        'BULBULA_MAIL_ENCRYPTION'        => 'mail.encryption',
        'BULBULA_MAIL_USERNAME'          => 'mail.username',
        'BULBULA_MAIL_PASSWORD'          => 'mail.password',
        'BULBULA_MAIL_FROM_EMAIL'        => 'mail.from_email',
        'BULBULA_MAIL_FROM_NAME'         => 'mail.from_name',
    ];

    public static function load(string $jsonFilePath): void
    {
        self::$configPath = $jsonFilePath;
        self::$items = self::readJson($jsonFilePath);

        $localPath = dirname($jsonFilePath) . '/env.local.json';
        if (is_file($localPath)) {
            self::$items = array_replace_recursive(self::$items, self::readJson($localPath));
        }

        foreach (self::ENV_MAP as $envName => $configKey) {
            $value = getenv($envName);
            if ($value === false || $value === '') {
                continue;
            }
            self::setRuntime($configKey, self::castEnvironmentValue($configKey, $value));
        }
    }

    private static function readJson(string $path): array
    {
        clearstatcache(true, $path);
        if (!is_file($path)) return [];
        $raw = file_get_contents($path);
        if ($raw === false) return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function castEnvironmentValue(string $key, string $value): mixed
    {
        if (in_array($key, ['app.debug', 'app.preview_features', 'security.require_staff_login'], true)) {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }
        if (in_array($key, ['database.port', 'mail.port', 'security.staff_session_idle_seconds', 'security.staff_session_absolute_seconds', 'security.customer_session_idle_seconds', 'security.customer_session_absolute_seconds'], true)) {
            return (int) $value;
        }
        return $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $current = self::$items;
        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) return $default;
            $current = $current[$segment];
        }
        return $current;
    }

    public static function all(): array
    {
        return self::$items;
    }

    public static function setRuntime(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $current = &self::$items;
        foreach ($segments as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) $current[$segment] = [];
            $current = &$current[$segment];
        }
        $current = $value;
    }

    public static function previewFeaturesEnabled(): bool
    {
        return (string) self::get('app.env', 'development') !== 'production'
            && (bool) self::get('app.preview_features', false);
    }

    /** @return string[] */
    public static function productionProblems(): array
    {
        if ((string) self::get('app.env', 'development') !== 'production') return [];
        $problems = [];
        $secret = (string) self::get('app.secret_key', '');
        if (strlen($secret) < 32 || str_contains($secret, 'change_this') || str_contains($secret, 'development')) {
            $problems[] = 'BULBULA_APP_SECRET must be a unique production secret of at least 32 characters.';
        }
        if (!(bool) self::get('security.require_staff_login', false)) {
            $problems[] = 'Production requires strict Staff Console authentication.';
        }
        if ((bool) self::get('app.debug', false)) {
            $problems[] = 'Production debug mode must be disabled.';
        }
        if ((bool) self::get('app.preview_features', false)) {
            $problems[] = 'Production preview features must be disabled.';
        }
        if ((string) self::get('database.driver', '') !== 'mysql') {
            $problems[] = 'Production requires the MySQL database driver; pilot SQLite data is forbidden.';
        }
        return $problems;
    }
}
