<?php
declare(strict_types=1);

namespace Bulbula\Database;

use PDO;
use RuntimeException;

/**
 * Dependency-free migration runner for MySQL/MariaDB and SQLite.
 * Uses versioned PHP migration files, checksums, transactions where supported,
 * and an exclusive filesystem deployment lock suitable for cPanel.
 */
final class MigrationRunner
{
    private string $directory;
    private string $lockPath;

    public function __construct(private readonly PDO $pdo, ?string $directory = null)
    {
        $root = defined('BULBULA_ROOT') ? BULBULA_ROOT : dirname(__DIR__, 2);
        $this->directory = $directory ?? $root . '/database/migrations';
        $this->lockPath = $root . '/storage/migration.lock';
        $this->ensureTable();
    }

    /** @return array<int,array{version:string,description:string,status:string}> */
    public function status(): array
    {
        $applied = $this->applied();
        $rows = [];
        foreach ($this->discover() as $version => $entry) {
            $rows[] = [
                'version' => $version,
                'description' => $entry['migration']->description(),
                'status' => isset($applied[$version]) ? 'APPLIED' : 'PENDING',
            ];
        }
        return $rows;
    }

    /** @return string[] applied versions */
    public function migrate(): array
    {
        return $this->withLock(function (): array {
            $applied = $this->applied();
            $batch = ((int) $this->pdo->query('SELECT COALESCE(MAX(batch), 0) FROM schema_migrations')->fetchColumn()) + 1;
            $completed = [];
            foreach ($this->discover() as $version => $entry) {
                if (isset($applied[$version])) {
                    if (!hash_equals($applied[$version]['checksum_sha256'], $entry['checksum'])) {
                        throw new RuntimeException("Applied migration {$version} checksum has changed; create a new migration instead.");
                    }
                    continue;
                }
                $this->transactional(function () use ($version, $entry, $batch): void {
                    $entry['migration']->up($this->pdo, $this->driver());
                    $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (version, description, checksum_sha256, batch, applied_at) VALUES (:v,:d,:c,:b,CURRENT_TIMESTAMP)');
                    $stmt->execute([':v'=>$version, ':d'=>$entry['migration']->description(), ':c'=>$entry['checksum'], ':b'=>$batch]);
                });
                $completed[] = $version;
            }
            return $completed;
        });
    }

    /** @return string[] rolled-back versions */
    public function rollbackLastBatch(): array
    {
        return $this->withLock(function (): array {
            $batch = (int) $this->pdo->query('SELECT COALESCE(MAX(batch), 0) FROM schema_migrations')->fetchColumn();
            if ($batch === 0) return [];
            $stmt = $this->pdo->prepare('SELECT version FROM schema_migrations WHERE batch = :b ORDER BY version DESC');
            $stmt->execute([':b' => $batch]);
            $versions = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $migrations = $this->discover();
            $rolledBack = [];
            foreach ($versions as $version) {
                $version = (string) $version;
                if (!isset($migrations[$version])) throw new RuntimeException("Migration file {$version} is missing; rollback stopped.");
                $this->transactional(function () use ($version, $migrations): void {
                    $migrations[$version]['migration']->down($this->pdo, $this->driver());
                    $stmt = $this->pdo->prepare('DELETE FROM schema_migrations WHERE version = :v');
                    $stmt->execute([':v' => $version]);
                });
                $rolledBack[] = $version;
            }
            return $rolledBack;
        });
    }

    private function ensureTable(): void
    {
        $id = $this->driver() === 'mysql' ? 'VARCHAR(64)' : 'TEXT';
        $desc = $this->driver() === 'mysql' ? 'VARCHAR(255)' : 'TEXT';
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            version {$id} NOT NULL PRIMARY KEY,
            description {$desc} NOT NULL,
            checksum_sha256 CHAR(64) NOT NULL,
            batch INTEGER NOT NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
    }

    /** @return array<string,array{migration:Migration,checksum:string}> */
    private function discover(): array
    {
        $entries = [];
        $files = glob($this->directory . '/*.php') ?: [];
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            $version = basename($file, '.php');
            if (!preg_match('/^[0-9]{14}_[a-z0-9_]+$/', $version)) throw new RuntimeException("Invalid migration filename: {$version}.php");
            $migration = require $file;
            if (!$migration instanceof Migration) throw new RuntimeException("Migration {$version} must return a Migration instance.");
            $checksum = hash_file('sha256', $file);
            if (!is_string($checksum)) throw new RuntimeException("Could not checksum migration {$version}.");
            $entries[$version] = ['migration' => $migration, 'checksum' => $checksum];
        }
        return $entries;
    }

    /** @return array<string,array{checksum_sha256:string}> */
    private function applied(): array
    {
        $rows = $this->pdo->query('SELECT version, checksum_sha256 FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) $result[(string) $row['version']] = ['checksum_sha256' => (string) $row['checksum_sha256']];
        return $result;
    }

    private function driver(): string
    {
        return (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    private function transactional(callable $operation): void
    {
        $started = !$this->pdo->inTransaction();
        if ($started) $this->pdo->beginTransaction();
        try {
            $operation();
            if ($started && $this->pdo->inTransaction()) $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($started && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function withLock(callable $operation): mixed
    {
        $handle = fopen($this->lockPath, 'c+b');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) throw new RuntimeException('Another database migration is already running.');
        try { return $operation(); }
        finally { flock($handle, LOCK_UN); fclose($handle); }
    }
}
