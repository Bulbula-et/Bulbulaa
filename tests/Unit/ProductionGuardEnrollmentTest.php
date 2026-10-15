<?php
declare(strict_types=1);

use Bulbula\Core\Config;
use Bulbula\Core\Database;
use Bulbula\Core\ProductionGuard;
use PHPUnit\Framework\TestCase;

final class ProductionGuardEnrollmentTest extends TestCase
{
    private ReflectionProperty $connection;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->connection = new ReflectionProperty(Database::class, 'pdo');
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE staff_users (id INTEGER PRIMARY KEY, email TEXT NOT NULL, role TEXT NOT NULL, password_hash TEXT NOT NULL, totp_enabled INTEGER NOT NULL, totp_secret_enc TEXT NULL, is_active INTEGER NOT NULL)');
        $this->connection->setValue(null, $this->pdo);
        Config::setRuntime('app.env', 'production');
        Config::setRuntime('app.debug', false);
        Config::setRuntime('app.preview_features', false);
        Config::setRuntime('app.secret_key', str_repeat('x', 48));
        Config::setRuntime('database.driver', 'mysql');
        Config::setRuntime('security.require_staff_login', true);
        Config::setRuntime('gates.D_45_STAFF_2FA_REQUIRED', true);
        $this->staff('ADMINISTRATOR', 'encrypted-test-secret', 'unique-admin-passphrase');
    }

    protected function tearDown(): void
    {
        $this->connection->setValue(null, null);
        Config::load(BULBULA_ROOT . '/config/env.json');
    }

    private function staff(string $role, ?string $secret, string $password): void
    {
        $id = (int)$this->pdo->query('SELECT COUNT(*) FROM staff_users')->fetchColumn() + 1;
        $stmt = $this->pdo->prepare('INSERT INTO staff_users(id,email,role,password_hash,totp_enabled,totp_secret_enc,is_active) VALUES(?,?,?,?,1,?,1)');
        $stmt->execute([$id, 'staff' . $id . '@example.test', $role, password_hash($password, PASSWORD_BCRYPT), $secret]);
    }

    public function testPendingOperatorEnrollmentDoesNotTakePublicSiteOffline(): void
    {
        $this->staff('OPERATOR', null, 'another-safe-passphrase');
        self::assertSame([], Config::productionProblems());
        self::assertSame([], ProductionGuard::problems());
    }

    public function testPendingAdministratorCanReachMandatoryEnrollment(): void
    {
        $this->staff('ADMINISTRATOR', null, 'another-safe-passphrase');
        self::assertSame([], ProductionGuard::problems());
    }

    public function testPilotPasswordStillBlocksProduction(): void
    {
        $this->staff('OPERATOR', null, 'BulbulaStaff2026!');
        self::assertNotSame([], ProductionGuard::problems());
    }

    public function testProductionRejectsDisabledMandatoryStaffTwoFactorGate(): void
    {
        Config::setRuntime('gates.D_45_STAFF_2FA_REQUIRED', false);
        self::assertContains('Production requires Staff two-factor enrollment.', Config::productionProblems());
    }

    public function testAtLeastOneActiveAdministratorStillRequired(): void
    {
        $this->pdo->exec("UPDATE staff_users SET is_active=0 WHERE role='ADMINISTRATOR'");
        self::assertContains('Production requires at least one active Staff administrator.', ProductionGuard::problems());
    }
}
