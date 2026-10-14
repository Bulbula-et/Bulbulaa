<?php
declare(strict_types=1);

use Bulbula\Core\Config;
use Bulbula\Services\ConfigurationService;
use PHPUnit\Framework\TestCase;

final class ConfigurationServiceTest extends TestCase
{
    private string $dir;
    private string $base;
    private string $local;
    private string $lock;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/bulbula-config-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);
        $this->base = $this->dir . '/env.json';
        $this->local = $this->dir . '/env.local.json';
        $this->lock = $this->dir . '/update.lock';
        file_put_contents($this->base, json_encode(['app'=>['env'=>'development'], 'security'=>[], 'auth'=>[], 'mail'=>[]], JSON_THROW_ON_ERROR));
        Config::load($this->base);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) @unlink($file);
        @rmdir($this->dir);
        Config::load(BULBULA_ROOT . '/config/env.json');
    }

    public function testValidUpdateIsAtomicRestrictedAndSecretsAreHidden(): void
    {
        $service = new ConfigurationService($this->base, $this->local, $this->lock);
        $summary = $service->update($this->validInput());
        self::assertSame('smtp.example.com', $summary['mail_host']);
        self::assertSame(0600, fileperms($this->local) & 0777);
        $saved = json_decode((string) file_get_contents($this->local), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('very-secret-password', $saved['mail']['password']);
        self::assertSame([], glob($this->local . '.tmp.*') ?: []);
        $snapshot = $service->safeSnapshot();
        self::assertArrayNotHasKey('password', $snapshot['mail']);
        self::assertTrue($snapshot['mail']['password_is_set']);
    }

    public function testUnknownOrInvalidFieldsNeverReplaceCurrentFile(): void
    {
        $service = new ConfigurationService($this->base, $this->local, $this->lock);
        $service->update($this->validInput());
        $before = file_get_contents($this->local);
        foreach ([['unexpected'=>'value'], ['mail_host'=>'https://bad.example']] as $change) {
            try {
                $service->update(array_replace($this->validInput(), $change));
                self::fail('Invalid configuration was accepted.');
            } catch (InvalidArgumentException) {
                self::assertSame($before, file_get_contents($this->local));
            }
        }
    }

    /** @return array<string,mixed> */
    private function validInput(): array
    {
        return [
            'require_staff_login'=>'1', 'otp_expiry_seconds'=>'600', 'review_edit_window_days'=>'30',
            'google_client_id'=>'123456-abc_xyz.apps.googleusercontent.com', 'google_client_secret'=>'google-secret-value',
            'telegram_bot_username'=>'BulbulaEtBot', 'telegram_bot_token'=>'123456789:abcdefghijklmnopqrstuvwxyz_ABCD',
            'mail_driver'=>'smtp', 'mail_host'=>'smtp.example.com', 'mail_port'=>'587', 'mail_encryption'=>'tls',
            'mail_username'=>'mailer@example.com', 'mail_password'=>'very-secret-password',
            'mail_from_email'=>'no-reply@example.com', 'mail_from_name'=>'Bulbula Verification',
        ];
    }
}
