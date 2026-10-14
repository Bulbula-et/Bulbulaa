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

require_once BULBULA_ROOT . '/src/Core/Autoloader.php';

use Bulbula\Core\Autoloader;
use Bulbula\Core\Config;
use Bulbula\Core\LiveReload;
use Bulbula\Core\Cache;
use Bulbula\Core\Session;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Router;

Autoloader::register(BULBULA_ROOT . '/src');
Config::load(BULBULA_ROOT . '/config/env.json');

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
