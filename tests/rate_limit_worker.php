<?php
declare(strict_types=1);
if ($argc !== 4) exit(2);
define('BULBULA_ROOT', dirname(__DIR__));
require BULBULA_ROOT . '/vendor/autoload.php';
require BULBULA_ROOT . '/src/Core/Autoloader.php';
\Bulbula\Core\Autoloader::register(BULBULA_ROOT . '/src');
\Bulbula\Core\Config::load(BULBULA_ROOT . '/config/env.json');
echo \Bulbula\Core\RateLimiter::allow((string) $argv[1], (int) $argv[2], (int) $argv[3]) ? "1\n" : "0\n";
