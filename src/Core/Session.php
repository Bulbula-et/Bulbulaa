<?php
declare(strict_types=1);

namespace Bulbula\Core;

use RuntimeException;

/** Isolated, hardened Staff and Customer PHP session manager for shared hosting. */
final class Session
{
    private const CREATED_AT = '_session_created_at';
    private const LAST_SEEN_AT = '_session_last_seen_at';
    private const REGENERATED_AT = '_session_regenerated_at';

    public static function startForPath(string $path): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;

        $sessionDir = (defined('BULBULA_ROOT') ? BULBULA_ROOT : dirname(__DIR__, 2)) . '/storage/sessions';
        if (!is_dir($sessionDir) && !mkdir($sessionDir, 0700, true) && !is_dir($sessionDir)) {
            throw new RuntimeException('Secure session storage is unavailable.');
        }
        chmod($sessionDir, 0700);
        session_save_path($sessionDir);
        if (session_save_path() !== $sessionDir) throw new RuntimeException('Unable to configure session storage.');

        $isStaffRoute = str_starts_with($path, '/staff');
        $cookieName = $isStaffRoute
            ? (string) Config::get('security.staff_session_name', 'bulbula_staff_sid')
            : (string) Config::get('security.customer_session_name', 'bulbula_cust_sid');
        $production = (string) Config::get('app.env', 'development') === 'production';
        $isHttps = $production || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
        $absolute = $isStaffRoute
            ? (int) Config::get('security.staff_session_absolute_seconds', 28800)
            : (int) Config::get('security.customer_session_absolute_seconds', 1209600);
        $idle = $isStaffRoute
            ? (int) Config::get('security.staff_session_idle_seconds', 1800)
            : (int) Config::get('security.customer_session_idle_seconds', 604800);

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_trans_sid', '0');
        session_name($cookieName);
        session_set_cookie_params([
            'lifetime' => $absolute,
            'path' => $isStaffRoute ? '/staff' : '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => $isStaffRoute ? 'Strict' : 'Lax',
        ]);
        if (!session_start()) throw new RuntimeException('Unable to start a secure session.');

        self::enforceLifecycle(max(300, $idle), max(600, $absolute));
    }

    private static function enforceLifecycle(int $idleSeconds, int $absoluteSeconds): void
    {
        $now = time();
        $created = (int) ($_SESSION[self::CREATED_AT] ?? $now);
        $lastSeen = (int) ($_SESSION[self::LAST_SEEN_AT] ?? $now);
        if (self::isExpired($created, $lastSeen, $now, $idleSeconds, $absoluteSeconds)) {
            self::invalidate();
            $_SESSION = [];
            if (!session_start()) throw new RuntimeException('Unable to renew an expired session.');
            $created = $now;
        }
        $_SESSION[self::CREATED_AT] = $created;
        $_SESSION[self::LAST_SEEN_AT] = $now;
        $regenerated = (int) ($_SESSION[self::REGENERATED_AT] ?? 0);
        if ($regenerated < $now - 900) self::regenerate(false);
    }

    private static function isExpired(int $created, int $lastSeen, int $now, int $idleSeconds, int $absoluteSeconds): bool
    {
        return $created < $now - $absoluteSeconds || $lastSeen < $now - $idleSeconds;
    }

    /** Non-sensitive lifecycle details for the signed-in user's session screen. */
    public static function metadata(): array
    {
        return [
            'created_at' => (int) ($_SESSION[self::CREATED_AT] ?? time()),
            'last_seen_at' => (int) ($_SESSION[self::LAST_SEEN_AT] ?? time()),
            'regenerated_at' => (int) ($_SESSION[self::REGENERATED_AT] ?? time()),
        ];
    }

    public static function get(string $key, mixed $default = null): mixed { return $_SESSION[$key] ?? $default; }
    public static function set(string $key, mixed $value): void { $_SESSION[$key] = $value; }
    public static function forget(string $key): void { unset($_SESSION[$key]); }
    public static function remove(string $key): void { self::forget($key); }

    public static function flash(string $key, ?string $message = null): ?string
    {
        if ($message !== null) { $_SESSION['_flash'][$key] = $message; return null; }
        $val = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return is_string($val) ? $val : null;
    }

    /** Regenerate the identifier and, by default, rotate CSRF after a trust change. */
    public static function regenerate(bool $rotateCsrf = true): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !session_regenerate_id(true)) {
            throw new RuntimeException('Unable to rotate the session identifier.');
        }
        $_SESSION[self::REGENERATED_AT] = time();
        if ($rotateCsrf) Csrf::rotate();
    }

    /** Clear all state and issue a new identifier while keeping this request's session active. */
    public static function reset(): void
    {
        $_SESSION = [];
        self::regenerate(true);
        $now = time();
        $_SESSION[self::CREATED_AT] = $now;
        $_SESSION[self::LAST_SEEN_AT] = $now;
    }

    /** Destroy server state and expire the current cookie. */
    public static function invalidate(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { $_SESSION = []; return; }
        $_SESSION = [];
        $params = session_get_cookie_params();
        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
        session_id('');
    }
}
