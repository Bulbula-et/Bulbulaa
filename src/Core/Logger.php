<?php
declare(strict_types=1);

namespace Bulbula\Core;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Throwable;

/** Small application logging adapter; keeps Monolog out of domain/application code. */
final class Logger
{
    private static ?MonologLogger $logger = null;
    private static string $requestId = '';

    public static function initialize(?string $requestId = null): void
    {
        self::$requestId = self::validRequestId($requestId) ? (string) $requestId : bin2hex(random_bytes(16));
        $path = (defined('BULBULA_ROOT') ? BULBULA_ROOT : dirname(__DIR__, 2)) . '/storage/logs/bulbula.log';
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create secure log directory.');
        }
        $handler = new RotatingFileHandler($path, 14, Level::Info, true, 0640);
        $handler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true));
        self::$logger = new MonologLogger('bulbula');
        self::$logger->pushHandler($handler);
    }

    public static function requestId(): string { return self::$requestId; }

    /** @param array<string,mixed> $context */
    public static function info(string $message, array $context = []): void { self::write('info', $message, $context); }
    /** @param array<string,mixed> $context */
    public static function warning(string $message, array $context = []): void { self::write('warning', $message, $context); }
    /** @param array<string,mixed> $context */
    public static function error(string $message, array $context = []): void { self::write('error', $message, $context); }

    public static function exception(Throwable $exception, array $context = []): void
    {
        $trace = [];
        foreach (array_slice($exception->getTrace(), 0, 30) as $frame) {
            $trace[] = [
                'file' => $frame['file'] ?? '',
                'line' => $frame['line'] ?? 0,
                'class' => $frame['class'] ?? '',
                'function' => $frame['function'] ?? '',
            ];
        }
        $context['exception'] = [
            'class' => $exception::class,
            'message_sha256' => hash('sha256', $exception->getMessage()),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $trace,
        ];
        self::error('Unhandled application exception', $context);
    }

    /** @param array<string,mixed> $context */
    private static function write(string $level, string $message, array $context): void
    {
        $context = self::redact($context);
        $context['request_id'] = self::$requestId;
        $context['environment'] = (string) Config::get('app.env', 'unknown');
        if (self::$logger !== null) {
            self::$logger->log($level, $message, $context);
            return;
        }
        error_log($message . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    private static function validRequestId(?string $value): bool
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9_-]{8,64}$/', $value) === 1;
    }

    /** @param array<string,mixed> $values @return array<string,mixed> */
    private static function redact(array $values): array
    {
        $sensitive = '/password|passwd|secret|token|authorization|cookie|otp|totp|code|session/i';
        foreach ($values as $key => $value) {
            if (preg_match($sensitive, (string) $key) === 1) $values[$key] = '[REDACTED]';
            elseif (is_array($value)) $values[$key] = self::redact($value);
        }
        return $values;
    }
}
