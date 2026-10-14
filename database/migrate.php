<?php
declare(strict_types=1);

/** Plain-PHP migration CLI: php database/migrate.php [migrate|status|rollback] */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BULBULA_ROOT', dirname(__DIR__));
require BULBULA_ROOT . '/vendor/autoload.php';
require BULBULA_ROOT . '/src/Core/Autoloader.php';
\Bulbula\Core\Autoloader::register(BULBULA_ROOT . '/src');
\Bulbula\Core\Config::load(BULBULA_ROOT . '/config/env.json');
$runner = new \Bulbula\Database\MigrationRunner(\Bulbula\Core\Database::pdo());
$command = $argv[1] ?? 'status';

try {
    if ($command === 'status') {
        foreach ($runner->status() as $row) printf("%-9s %-48s %s\n", $row['status'], $row['version'], $row['description']);
        exit(0);
    }
    if ($command === 'migrate') {
        $versions = $runner->migrate();
        echo $versions === [] ? "Database is already current.\n" : "Applied: " . implode(', ', $versions) . "\n";
        exit(0);
    }
    if ($command === 'rollback') {
        $versions = $runner->rollbackLastBatch();
        echo $versions === [] ? "Nothing to roll back.\n" : "Rolled back: " . implode(', ', $versions) . "\n";
        exit(0);
    }
    fwrite(STDERR, "Usage: php database/migrate.php [status|migrate|rollback]\n");
    exit(2);
} catch (Throwable $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
