<?php
declare(strict_types=1);

use Bulbula\Core\SecurityHeaders;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersTest extends TestCase
{
    public function testNonceIsStableForRequestAndCryptographicallySized(): void
    {
        $first = SecurityHeaders::nonce();
        self::assertSame($first, SecurityHeaders::nonce());
        $decoded = base64_decode($first, true);
        self::assertIsString($decoded);
        self::assertSame(18, strlen($decoded));
    }
}
