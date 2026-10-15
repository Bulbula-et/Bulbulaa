<?php
declare(strict_types=1);

/**
 * Non-production SQLite backup/restore rehearsal.
 * Creates an online-consistent backup, restores it to an isolated temporary
 * database, verifies integrity and table row-count parity, then removes it.
 */
$root = dirname(__DIR__);
define('BULBULA_ROOT', $root);
require $root . '/vendor/autoload.php';
require $root . '/src/Core/Autoloader.php';
\Bulbula\Core\Autoloader::register($root . '/src');
\Bulbula\Core\Config::load($root . '/config/env.json');

$source = \Bulbula\Core\Database::pdo();
if ($source->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
    fwrite(STDERR, "[SKIP] SQLite rehearsal applies only to the CI/non-production SQLite profile. Use docs/SHARED_HOST_RECOVERY.md for MariaDB.\n");
    exit(0);
}

$dir = $root . '/storage/backups/rehearsal';
if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
    throw new RuntimeException('Could not create isolated rehearsal directory');
}
$backup = $dir . '/backup-' . bin2hex(random_bytes(6)) . '.sqlite';
$restored = $dir . '/restore-' . bin2hex(random_bytes(6)) . '.sqlite';

try {
    $source->exec("VACUUM INTO " . $source->quote($backup));
    if (!copy($backup, $restored)) throw new RuntimeException('Could not restore backup copy');
    $target = new PDO('sqlite:' . $restored, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if ($target->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') throw new RuntimeException('Restored database failed integrity_check');

    $tables = $source->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    if ($tables === []) throw new RuntimeException('Source database has no application tables');
    foreach ($tables as $table) {
        $quoted = '"' . str_replace('"', '""', (string) $table) . '"';
        $sourceCount = (int) $source->query("SELECT COUNT(*) FROM {$quoted}")->fetchColumn();
        $targetCount = (int) $target->query("SELECT COUNT(*) FROM {$quoted}")->fetchColumn();
        if ($sourceCount !== $targetCount) throw new RuntimeException("Row-count mismatch in {$table}");
    }
    printf("[PASS] Isolated SQLite restore: integrity ok; %d table counts match; production untouched.\n", count($tables));
} finally {
    @unlink($backup);
    @unlink($restored);
    @rmdir($dir);
}
