<?php
declare(strict_types=1);

namespace Bulbula\Core;

use Throwable;

/** Centralized, fail-safe HTTP exception/error handling with correlation IDs. */
final class ErrorHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleException(Throwable $exception): void
    {
        Logger::exception($exception, self::requestContext());
        self::sendSafeResponse(500);
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) return false;
        Logger::warning('PHP runtime error', self::requestContext() + [
            'severity' => $severity, 'message_sha256' => hash('sha256', $message), 'file' => $file, 'line' => $line,
        ]);
        return true;
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) return;
        Logger::error('Fatal PHP shutdown error', self::requestContext() + [
            'severity' => $error['type'], 'message_sha256' => hash('sha256', $error['message']), 'file' => $error['file'], 'line' => $error['line'],
        ]);
        self::sendSafeResponse(500);
    }

    /** @return array<string,mixed> */
    private static function requestContext(): array
    {
        return [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'path' => isset($_SERVER['REQUEST_URI']) ? (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) : '',
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ];
    }

    private static function sendSafeResponse(int $status): void
    {
        if (PHP_SAPI === 'cli') return;
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store');
            header('X-Request-ID: ' . Logger::requestId());
        }
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width">'
            . '<title>Service unavailable · Bulbula.et</title><body><main><h1>Something went wrong</h1>'
            . '<p>Please try again. If the problem continues, reference request <code>'
            . htmlspecialchars(Logger::requestId(), ENT_QUOTES, 'UTF-8') . '</code>.</p></main></body></html>';
    }
}
