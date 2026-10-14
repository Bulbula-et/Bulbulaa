<?php
declare(strict_types=1);

use Bulbula\Core\Logger;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    /** @var string[] */
    private array $before = [];

    protected function setUp(): void
    {
        $this->before = glob(BULBULA_ROOT . '/storage/logs/bulbula-*.log') ?: [];
    }

    protected function tearDown(): void
    {
        foreach (glob(BULBULA_ROOT . '/storage/logs/bulbula-*.log') ?: [] as $file) {
            if (!in_array($file, $this->before, true)) @unlink($file);
        }
    }

    public function testStructuredLogHasCorrelationIdAndRedactsSecrets(): void
    {
        Logger::initialize('test-request-1234');
        Logger::info('Unit log event', [
            'route' => '/health',
            'password' => 'never-write-this',
            'nested' => ['authorization' => 'Bearer private-token'],
        ]);

        $files = glob(BULBULA_ROOT . '/storage/logs/bulbula-*.log') ?: [];
        self::assertNotEmpty($files);
        $contents = file_get_contents(end($files));
        self::assertIsString($contents);
        $lines = array_values(array_filter(explode("\n", trim($contents)), static fn(string $line): bool => $line !== ''));
        $line = (string) end($lines);
        $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('test-request-1234', $decoded['context']['request_id']);
        self::assertSame('[REDACTED]', $decoded['context']['password']);
        self::assertSame('[REDACTED]', $decoded['context']['nested']['authorization']);
        self::assertStringNotContainsString('never-write-this', $line);
        self::assertStringNotContainsString('private-token', $line);
    }

    public function testUntrustedRequestIdIsReplaced(): void
    {
        Logger::initialize("bad\r\nheader");
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', Logger::requestId());
    }
}
