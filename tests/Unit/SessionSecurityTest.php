<?php
declare(strict_types=1);

use Bulbula\Core\Csrf;
use Bulbula\Core\Session;
use PHPUnit\Framework\TestCase;

final class SessionSecurityTest extends TestCase
{
    private array $originalSession;

    protected function setUp(): void
    {
        $this->originalSession = $_SESSION ?? [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;
    }

    public function testCsrfRotationInvalidatesPreviousToken(): void
    {
        $old = Csrf::token();
        self::assertTrue(Csrf::verify($old));
        $new = Csrf::rotate();
        self::assertNotSame($old, $new);
        self::assertFalse(Csrf::verify($old));
        self::assertTrue(Csrf::verify($new));
    }

    public function testIdleAndAbsoluteTimeoutRules(): void
    {
        $method = new ReflectionMethod(Session::class, 'isExpired');
        self::assertFalse($method->invoke(null, 900, 950, 1000, 100, 200));
        self::assertTrue($method->invoke(null, 900, 899, 1000, 100, 200));
        self::assertTrue($method->invoke(null, 799, 999, 1000, 100, 200));
    }
}
