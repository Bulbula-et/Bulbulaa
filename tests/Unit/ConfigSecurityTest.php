<?php
declare(strict_types=1);

use Bulbula\Core\Config;
use Bulbula\Core\ProductionGuard;
use PHPUnit\Framework\TestCase;

final class ConfigSecurityTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/bulbula-config-' . bin2hex(random_bytes(5));
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        putenv('BULBULA_APP_ENV');
        putenv('BULBULA_APP_SECRET');
        foreach (glob($this->dir . '/*') ?: [] as $file) @unlink($file);
        @rmdir($this->dir);
        Config::load(BULBULA_ROOT . '/config/env.json');
    }

    public function testLocalOverlayAndEnvironmentPrecedence(): void
    {
        file_put_contents($this->dir . '/env.json', json_encode([
            'app' => ['env' => 'development', 'secret_key' => 'base'],
            'mail' => ['password' => ''],
        ]));
        file_put_contents($this->dir . '/env.local.json', json_encode([
            'mail' => ['password' => 'local-secret'],
        ]));
        putenv('BULBULA_APP_SECRET=environment-secret-value-1234567890');

        Config::load($this->dir . '/env.json');
        self::assertSame('local-secret', Config::get('mail.password'));
        self::assertSame('environment-secret-value-1234567890', Config::get('app.secret_key'));
    }

    public function testUnsafeProductionConfigurationFailsPreflight(): void
    {
        file_put_contents($this->dir . '/env.json', json_encode([
            'app' => ['env' => 'production', 'debug' => true, 'preview_features' => true, 'secret_key' => 'development-secret'],
            'security' => ['require_staff_login' => false],
            'database' => ['driver' => 'sqlite'],
        ]));
        Config::load($this->dir . '/env.json');
        self::assertCount(5, Config::productionProblems());
    }

    public function testKnownPilotPasswordsAreRejected(): void
    {
        self::assertTrue(ProductionGuard::isKnownPilotPassword(password_hash('password', PASSWORD_BCRYPT)));
        self::assertTrue(ProductionGuard::isKnownPilotPassword(password_hash('BulbulaStaff2026!', PASSWORD_BCRYPT)));
        self::assertFalse(ProductionGuard::isKnownPilotPassword(password_hash('unique-production-value-49!xQ', PASSWORD_BCRYPT)));
    }

    public function testSafeProductionConfigurationPassesPreflight(): void
    {
        file_put_contents($this->dir . '/env.json', json_encode([
            'app' => ['env' => 'production', 'debug' => false, 'preview_features' => false, 'secret_key' => str_repeat('a', 48)],
            'security' => ['require_staff_login' => true],
            'database' => ['driver' => 'mysql'],
        ]));
        Config::load($this->dir . '/env.json');
        self::assertSame([], Config::productionProblems());
        self::assertFalse(Config::previewFeaturesEnabled());
    }
}
