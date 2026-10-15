<?php
declare(strict_types=1);

namespace Bulbula\Core;

/** Applies one centralized browser security policy to every HTTP response. */
final class SecurityHeaders
{
    private static string $nonce = '';

    public static function apply(): void
    {
        if (self::$nonce === '') self::$nonce = base64_encode(random_bytes(18));
        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "form-action 'self'",
            "frame-ancestors 'self' https://web.telegram.org https://*.telegram.org",
            "script-src 'self' 'nonce-" . self::$nonce . "' https://accounts.google.com https://telegram.org",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self' https://accounts.google.com",
            "frame-src https://accounts.google.com",
            "media-src 'self'",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
        ];
        if ((string) Config::get('app.env', 'development') === 'production') $directives[] = 'upgrade-insecure-requests';
        header('Content-Security-Policy: ' . implode('; ', $directives));
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(self), payment=(), usb=(), serial=(), bluetooth=(), browsing-topics=()');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Content-Type-Options: nosniff');
        header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
        header('Cross-Origin-Resource-Policy: same-site');
        header('Origin-Agent-Cluster: ?1');
        if ((string) Config::get('app.env', 'development') === 'production') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function nonce(): string
    {
        if (self::$nonce === '') self::$nonce = base64_encode(random_bytes(18));
        return self::$nonce;
    }
}
