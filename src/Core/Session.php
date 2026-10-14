<?php
declare(strict_types=1);

namespace Bulbula\Core;

/**
 * Isolated Session Manager.
 * Separates Staff Console sessions (`bulbula_staff_sid`) from Public/Customer sessions (`bulbula_cust_sid`)
 * and stores session files inside `/storage/sessions` to prevent shared hosting `/tmp` purges.
 */
final class Session
{
    public static function startForPath(string $path): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $sessionDir = (defined('BULBULA_ROOT') ? BULBULA_ROOT : dirname(__DIR__, 2)) . '/storage/sessions';
        if (!is_dir($sessionDir)) {
            @mkdir($sessionDir, 0700, true);
        }
        @session_save_path($sessionDir);

        $isStaffRoute = str_starts_with($path, '/staff');
        $cookieName   = $isStaffRoute
            ? (string) Config::get('security.staff_session_name', 'bulbula_staff_sid')
            : (string) Config::get('security.customer_session_name', 'bulbula_cust_sid');

        session_name($cookieName);
        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        session_set_cookie_params([
            'lifetime' => $isStaffRoute ? 28800 : 1209600, // 8h Staff vs 14d Customer
            'path'     => $isStaffRoute ? '/staff' : '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => $isStaffRoute ? 'Strict' : 'Lax',
        ]);

        @session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::forget($key);
    }

    public static function flash(string $key, ?string $message = null): ?string
    {
        if ($message !== null) {
            $_SESSION['_flash'][$key] = $message;
            return null;
        }
        $val = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return is_string($val) ? $val : null;
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
    }
}
