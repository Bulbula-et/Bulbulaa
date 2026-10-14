<?php
declare(strict_types=1);

namespace Bulbula\Core;

/**
 * Clean HTTP Request Wrapper with Launch-Surface Detection (Web vs. Telegram Mini App).
 */
final class Request
{
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array $queryParams,
        private readonly array $postParams,
        private readonly array $serverParams,
        private array $routeParams = [],
        private readonly array $uploadedFiles = []
    ) {}

    public static function capture(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri    = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        return new self($method, $uri, $_GET, $_POST, $_SERVER, [], $_FILES);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        $path = parse_url($this->uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '/';
        }
        $normalized = '/' . trim($path, '/');
        return $normalized === '//' ? '/' : $normalized;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->queryParams;
        }
        return $this->queryParams[$key] ?? $default;
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->postParams;
        }
        return $this->postParams[$key] ?? $default;
    }

    /** @return array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int}|null */
    public function file(string $key): ?array
    {
        $file = $this->uploadedFiles[$key] ?? null;
        return is_array($file) ? $file : null;
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * Detects whether the visitor is inside the Telegram Mini App surface (Page 3).
     * Note per C-32: Surface detection is for UI/navigation adaptation only, NEVER trusted as Customer identity.
     */
    public function isTelegramMiniApp(): bool
    {
        if (($this->queryParams['surface'] ?? '') === 'tg' || isset($this->queryParams['tgWebAppPlatform'])) {
            return true;
        }
        $header = (string) ($this->serverParams['HTTP_X_TELEGRAM_MINI_APP'] ?? '');
        return $header === '1';
    }

    public function surface(): string
    {
        return $this->isTelegramMiniApp() ? 'TELEGRAM_MINI_APP' : 'WEB';
    }

    public function clientIpHash(): string
    {
        $ip = (string) ($this->serverParams['REMOTE_ADDR'] ?? '0.0.0.0');
        $secret = (string) Config::get('app.secret_key', 'bulbula');
        return hash_hmac('sha256', $ip, $secret);
    }
}
