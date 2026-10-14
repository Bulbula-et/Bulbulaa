<?php
declare(strict_types=1);

/** Driver-neutral test query helper used only by authenticated E2E scripts. */
define('BULBULA_ROOT', dirname(__DIR__));
require BULBULA_ROOT . '/vendor/autoload.php';
require BULBULA_ROOT . '/src/Core/Autoloader.php';
\Bulbula\Core\Autoloader::register(BULBULA_ROOT . '/src');
\Bulbula\Core\Config::load(BULBULA_ROOT . '/config/env.json');

$mode = $argv[1] ?? '';
$sql = $argv[2] ?? '';
$params = array_slice($argv, 3);
$stmt = \Bulbula\Core\Database::pdo()->prepare($sql);
$stmt->execute($params);
if ($mode === 'scalar') {
    $value = $stmt->fetchColumn();
    echo $value === false ? '' : (string) $value;
    exit;
}
if ($mode === 'execute') exit;
fwrite(STDERR, "Usage: db_test_tool.php [scalar|execute] SQL [positional params...]\n");
exit(2);
