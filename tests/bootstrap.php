<?php
declare(strict_types=1);

define('BULBULA_ROOT', dirname(__DIR__));
define('BULBULA_PUBLIC', BULBULA_ROOT . '/public_html');
require BULBULA_ROOT . '/vendor/autoload.php';
require BULBULA_ROOT . '/src/Core/Autoloader.php';
\Bulbula\Core\Autoloader::register(BULBULA_ROOT . '/src');
\Bulbula\Core\Config::load(BULBULA_ROOT . '/config/env.json');
date_default_timezone_set((string) \Bulbula\Core\Config::get('app.timezone', 'Africa/Addis_Ababa'));
