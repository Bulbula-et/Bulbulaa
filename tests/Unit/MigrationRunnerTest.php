<?php
declare(strict_types=1);

use Bulbula\Database\MigrationRunner;
use PHPUnit\Framework\TestCase;

final class MigrationRunnerTest extends TestCase
{
    private string $dbFile;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->dbFile = tempnam(sys_get_temp_dir(), 'bulbula-migration-');
        $this->pdo = new PDO('sqlite:' . $this->dbFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        foreach (['staff_users','categories','areas','businesses','branches','business_permissions','media_assets','customers','reviews','audit_logs'] as $table) {
            $this->pdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY)");
        }
        @unlink(BULBULA_ROOT . '/storage/migration.lock');
    }

    protected function tearDown(): void
    {
        @unlink($this->dbFile);
        @unlink(BULBULA_ROOT . '/storage/migration.lock');
    }

    public function testBaselineAppliesOnceAndRollbackIsNonDestructive(): void
    {
        $runner = new MigrationRunner($this->pdo, BULBULA_ROOT . '/database/migrations');
        self::assertSame('PENDING', $runner->status()[0]['status']);
        self::assertSame([
            '20261014000000_baseline_v1_schema',
            '20261015010000_customer_password_profiles',
            '20261015020000_customer_otp_purposes',
        ], $runner->migrate());
        self::assertSame('APPLIED', $runner->status()[0]['status']);
        self::assertSame([], $runner->migrate());
        self::assertSame([
            '20261015020000_customer_otp_purposes',
            '20261015010000_customer_password_profiles',
            '20261014000000_baseline_v1_schema',
        ], $runner->rollbackLastBatch());
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='branches'")->fetchColumn());
    }

    public function testBaselineRefusesIncompleteDatabase(): void
    {
        $this->pdo->exec('DROP TABLE reviews');
        $runner = new MigrationRunner($this->pdo, BULBULA_ROOT . '/database/migrations');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('required table reviews is missing');
        $runner->migrate();
    }
}
