<?php
declare(strict_types=1);

/**
 * Bulbula.et V1 — Single Front Controller
 * Designed for shared hosting (Apache / LiteSpeed / PHP 8.2+)
 * Zero server-restart architecture: live config + generation-tagged file cache.
 */

define('BULBULA_START_TIME', microtime(true));
define('BULBULA_ROOT', dirname(__DIR__));
define('BULBULA_PUBLIC', __DIR__);

$composerAutoload = BULBULA_ROOT . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}
require_once BULBULA_ROOT . '/src/Core/Autoloader.php';

use Bulbula\Core\Autoloader;
use Bulbula\Core\Config;
use Bulbula\Core\LiveReload;
use Bulbula\Core\Cache;
use Bulbula\Core\Logger;
use Bulbula\Core\ErrorHandler;
use Bulbula\Core\ProductionGuard;
use Bulbula\Core\Session;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Router;

Autoloader::register(BULBULA_ROOT . '/src');
Config::load(BULBULA_ROOT . '/config/env.json');
Logger::initialize(isset($_SERVER['HTTP_X_REQUEST_ID']) ? (string) $_SERVER['HTTP_X_REQUEST_ID'] : null);
ErrorHandler::register();
header('X-Request-ID: ' . Logger::requestId());

// Fail closed when a production deployment still contains unsafe development configuration.
$productionProblems = Config::productionProblems();
if ($productionProblems === []) $productionProblems = ProductionGuard::problems();
if ($productionProblems !== []) {
    Logger::error('Production configuration blocked', ['problems' => $productionProblems]);
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo 'Bulbula.et is temporarily unavailable while secure production configuration is completed.';
    exit;
}

date_default_timezone_set((string) Config::get('app.timezone', 'Africa/Addis_Ababa'));

// 1. Automatic runtime OPcache / stat-cache check (prevents frozen PHP files on shared hosting)
if ((bool) Config::get('cache.auto_opcache_check', true)) {
    LiveReload::autoRefreshModifiedScripts(BULBULA_ROOT);
}

// 2. Emergency browser-triggered cache & OPcache flush (never requires SSH or server restart)
$request = Request::capture();
$flushToken = (string) Config::get('app.cache_refresh_token', '');
if ($flushToken !== '' && hash_equals($flushToken, (string) $request->query('__flush', ''))) {
    $stats = Cache::flushAll();
    $opcacheCleared = LiveReload::forceFlushAll(BULBULA_ROOT);
    Response::json([
        'status'          => 'ok',
        'message'         => 'All application file caches and PHP OPcache entries refreshed without server restart.',
        'cache_stats'     => $stats,
        'opcache_cleared' => $opcacheCleared,
        'timestamp'       => date('c'),
    ])->send();
    exit;
}

// 3. Initialize isolated Session realm (Staff Console vs. Public/Customer)
Session::startForPath($request->path());

// 4. Dispatch request through declarative Router
$router = new Router(require BULBULA_ROOT . '/config/routes.php');
$response = $router->dispatch($request);
$response->send();
