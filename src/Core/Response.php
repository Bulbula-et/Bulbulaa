<?php
declare(strict_types=1);

namespace Bulbula\Core;

/**
 * HTTP Response Emitter with Explicit No-Stale-HTML Headers.
 */
final class Response
{
    public function __construct(
        private readonly string $body,
        private readonly int $statusCode = 200,
        private readonly array $headers = []
    ) {}

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, [
            'Content-Type'  => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            [
                'Content-Type'  => 'application/json; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]
        );
    }

    public static function download(string $body, string $filename, string $contentType = 'application/octet-stream'): self
    {
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) ?: 'download';
        return new self($body, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $safeName . '"',
            'Content-Length' => (string) strlen($body),
            'Cache-Control' => 'no-store, private, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, [
            'Location'      => $url,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function send(): void
    {
        // Always release any active DB connection before finishing output on shared hosting
        Database::disconnect();

        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        echo $this->body;
    }
}
