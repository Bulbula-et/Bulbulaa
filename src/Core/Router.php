<?php
declare(strict_types=1);

namespace Bulbula\Core;

/**
 * Lightweight Regex Router for Public, Customer, and Staff Console surfaces.
 */
final class Router
{
    public function __construct(private readonly array $routes)
    {
        foreach ($routes as $route) {
            $path = (string) ($route[1] ?? '');
            if (str_starts_with($path, '/staff') && !isset($route[3]['access'])) {
                throw new \InvalidArgumentException("Staff route {$path} is missing an explicit access policy.");
            }
        }
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path   = $request->path();

        foreach ($this->routes as $route) {
            [$routeMethod, $routePattern, $handler] = $route;

            if ($routeMethod !== $method) {
                continue;
            }

            $regex = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', static function (array $matches): string {
                return '(?P<' . $matches[1] . '>[^/]+)';
            }, $routePattern);

            $regex = '#^' . $regex . '$#u';

            if (preg_match($regex, $path, $matches)) {
                $params = [];
                foreach ($matches as $k => $v) {
                    if (is_string($k)) {
                        $params[$k] = rawurldecode($v);
                    }
                }
                $request->setRouteParams($params);

                [$controllerClass, $action] = $handler;

                if (str_starts_with($path, '/staff')) {
                    $policy = new \Bulbula\Services\StaffAccessPolicy();
                    $denied = $policy->authorize((string) ($route[3]['access'] ?? ''), $request);
                    if ($denied !== null) return $denied;
                }

                $fqcn = 'Bulbula\\Controllers\\' . $controllerClass;

                if (!class_exists($fqcn)) {
                    return Response::html('Controller not found: ' . htmlspecialchars($fqcn), 500);
                }

                $controller = new $fqcn();
                if (!method_exists($controller, $action)) {
                    return Response::html('Action not found: ' . htmlspecialchars($action), 500);
                }

                return $controller->{$action}($request);
            }
        }

        return Response::html(
            View::renderStandaloneError(
                404,
                'Page Not Found',
                'The page or branch listing you requested could not be found in the Bulbula.et directory.'
            ),
            404
        );
    }
}
