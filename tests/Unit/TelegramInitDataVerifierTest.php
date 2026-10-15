<?php
declare(strict_types=1);

use Bulbula\Core\Config;
use Bulbula\Services\TelegramInitDataVerifier;
use PHPUnit\Framework\TestCase;

final class TelegramInitDataVerifierTest extends TestCase
{
    private const TOKEN = '123456:ci-telegram-bot-token-not-real';
    private TelegramInitDataVerifier $verifier;

    protected function setUp(): void
    {
        Config::setRuntime('auth.telegram_bot_token', self::TOKEN);
        $this->verifier = new TelegramInitDataVerifier();
    }

    protected function tearDown(): void
    {
        Config::setRuntime('auth.telegram_bot_token', '');
    }

    /** Independently synthesize the official Telegram HMAC, not the vendor hash helper. */
    private function signed(string $user = '{"id":123456789,"first_name":"Test"}', ?int $timestamp = null, string $token = self::TOKEN): string
    {
        $fields = ['auth_date'=>(string)($timestamp ?? time()), 'query_id'=>'test-query', 'user'=>$user];
        ksort($fields, SORT_STRING);
        $check = implode("\n", array_map(static fn($k,$v)=>$k.'='.$v, array_keys($fields), $fields));
        $secret = hash_hmac('sha256', $token, 'WebAppData', true);
        $fields['hash'] = hash_hmac('sha256', $check, $secret);
        return http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
    }

    public function testValidIdentityAndNoTrustInUnverifiedData(): void
    {
        $raw = $this->signed();
        self::assertSame('123456789', $this->verifier->verify($raw));
        self::assertNull($this->verifier->verify(str_replace('123456789', '123456790', $raw)));
        self::assertNull($this->verifier->verify($this->signed(token:'999:wrong-bot')));
        self::assertNull($this->verifier->verify($raw.'&user=%7B%22id%22%3A1%7D'));
        self::assertNull($this->verifier->verify($raw.'&auth.date=123'));
        self::assertNull($this->verifier->verify($raw.'&user%5Bid%5D=123'));
    }

    public function testFreshnessAndMissingUser(): void
    {
        self::assertNull($this->verifier->verify($this->signed(timestamp:time()-301)));
        self::assertNull($this->verifier->verify($this->signed(timestamp:time()+61)));
        self::assertNull($this->verifier->verify($this->signed(user:'{"first_name":"No id"}')));
        Config::setRuntime('auth.telegram_bot_token', '');
        self::assertNull($this->verifier->verify($this->signed()));
    }
}
