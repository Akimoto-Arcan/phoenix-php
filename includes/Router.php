<?php

namespace Phoenix;

class Router
{
    private static $routes = [];
    private static $groupStack = [];
    private static $currentRoute = null;

    public static function get(string $pattern, $handler): void
    {
        self::addRoute('GET', $pattern, $handler);
    }

    public static function post(string $pattern, $handler): void
    {
        self::addRoute('POST', $pattern, $handler);
    }

    public static function put(string $pattern, $handler): void
    {
        self::addRoute('PUT', $pattern, $handler);
    }

    public static function delete(string $pattern, $handler): void
    {
        self::addRoute('DELETE', $pattern, $handler);
    }

    public static function any(string $pattern, $handler): void
    {
        self::addRoute('ANY', $pattern, $handler);
    }

    public static function group(array $attributes, callable $callback): void
    {
        self::$groupStack[] = $attributes;
        $callback();
        array_pop(self::$groupStack);
    }

    public static function dispatch(string $method, string $uri): void
    {
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
        $uri = '/' . trim($uri, '/');
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }

        $matched = self::match(strtoupper($method), $uri);

        if ($matched === null) {
            self::sendNotFound($uri);
            return;
        }

        self::$currentRoute = $matched;

        if (!self::runMiddleware($matched['middleware'] ?? [])) {
            return;
        }

        $handler = $matched['handler'];
        $params = $matched['params'] ?? [];

        if (is_callable($handler)) {
            $result = call_user_func($handler, $params);
            if (is_string($result)) {
                echo $result;
            }
        } elseif (is_string($handler)) {
            echo View::render($handler, $params);
        }
    }

    public static function moduleApiProxy(string $moduleName, string $routeFile): void
    {
        $prefix = self::currentPrefix();
        self::addRoute('ANY', "/api/v1/{$moduleName}/{path...}", function ($params) use ($routeFile) {
            $_GET['path'] = $params['path'] ?? '';
            require $routeFile;
        });
    }

    public static function currentRoute(): ?array
    {
        return self::$currentRoute;
    }

    public static function reset(): void
    {
        self::$routes = [];
        self::$groupStack = [];
        self::$currentRoute = null;
    }

    private static function addRoute(string $method, string $pattern, $handler): void
    {
        $prefix = self::currentPrefix();
        $middleware = self::currentMiddleware();

        $fullPattern = '/' . trim($prefix . '/' . trim($pattern, '/'), '/');
        if ($fullPattern !== '/') {
            $fullPattern = rtrim($fullPattern, '/');
        }

        self::$routes[] = [
            'method' => $method,
            'pattern' => $fullPattern,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    private static function currentPrefix(): string
    {
        $prefix = '';
        foreach (self::$groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
        }
        return $prefix;
    }

    private static function currentMiddleware(): array
    {
        $middleware = [];
        foreach (self::$groupStack as $group) {
            if (isset($group['middleware'])) {
                $middleware = array_merge($middleware, (array)$group['middleware']);
            }
        }
        return $middleware;
    }

    private static function match(string $method, string $uri): ?array
    {
        foreach (self::$routes as $route) {
            if ($route['method'] !== 'ANY' && $route['method'] !== $method) {
                continue;
            }

            $params = self::matchPattern($route['pattern'], $uri);
            if ($params !== null) {
                return array_merge($route, ['params' => $params]);
            }
        }

        return null;
    }

    private static function matchPattern(string $pattern, string $uri): ?array
    {
        $patternParts = $pattern === '/' ? [''] : explode('/', trim($pattern, '/'));
        $uriParts = $uri === '/' ? [''] : explode('/', trim($uri, '/'));

        $params = [];

        for ($i = 0; $i < count($patternParts); $i++) {
            $part = $patternParts[$i];

            // Catch-all: {name...}
            if (preg_match('/^\{(\w+)\.\.\.\}$/', $part, $m)) {
                $params[$m[1]] = implode('/', array_slice($uriParts, $i));
                return $params;
            }

            if (!isset($uriParts[$i])) {
                return null;
            }

            // Named parameter: {name}
            if (preg_match('/^\{(\w+)\}$/', $part, $m)) {
                $params[$m[1]] = urldecode($uriParts[$i]);
                continue;
            }

            // Literal match
            if ($part !== $uriParts[$i]) {
                return null;
            }
        }

        if (count($uriParts) !== count($patternParts)) {
            return null;
        }

        return $params;
    }

    private static function runMiddleware(array $middleware): bool
    {
        foreach ($middleware as $mw) {
            if ($mw === 'auth') {
                Auth::require('/login');
            } elseif ($mw === 'guest') {
                if (Auth::check()) {
                    redirect('/dashboard');
                    return false;
                }
            } elseif ($mw === 'csrf') {
                CSRF::requireToken();
            } elseif (strpos($mw, 'permission:') === 0) {
                $permission = substr($mw, 11);
                Auth::requirePermission($permission);
            }
        }

        return true;
    }

    private static function sendNotFound(string $uri): void
    {
        http_response_code(404);

        if (strpos($uri, '/api/') === 0) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Not found']);
            return;
        }

        if (class_exists('Phoenix\\View') && View::viewExists('errors.404')) {
            echo View::render('errors.404', [], 'auth');
        } else {
            echo '<h1>404 Not Found</h1><p>The page you requested could not be found.</p>';
        }
    }
}
