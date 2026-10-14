<?php
declare(strict_types=1);

use Bulbula\Core\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    public function testLimitAndWindowExpiry(): void
    {
        $key = 'unit-' . bin2hex(random_bytes(8));
        RateLimiter::clear($key);
        self::assertTrue(RateLimiter::allow($key, 2, 60, 1000));
        self::assertTrue(RateLimiter::allow($key, 2, 60, 1001));
        self::assertFalse(RateLimiter::allow($key, 2, 60, 1002));
        self::assertTrue(RateLimiter::allow($key, 2, 60, 1061));
        RateLimiter::clear($key);
    }

    public function testInvalidPolicyFailsClosed(): void
    {
        self::assertFalse(RateLimiter::allow('', 5, 60));
        self::assertFalse(RateLimiter::allow('x', 0, 60));
        self::assertFalse(RateLimiter::allow('x', 5, 0));
    }
}
