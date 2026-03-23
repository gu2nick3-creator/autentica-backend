<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable|array $handler, bool $auth = false): void
    {
        $this->routes[] = compact('method', 'path', 'handler', 'auth');
    }

    public function dispatch(Request $request): void
    {
        $uri = $request->uri();
        $method = $request->method();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if (!preg_match($pattern, $uri, $matches)) {
                continue;
            }

            if ($route['auth']) {
                $user = \App\Services\AuthService::guard($request);
                if (!$user) {
                    jsonResponse(['ok' => false, 'message' => 'Não autorizado.'], 401);
                    return;
                }
            }

            $params = array_filter($matches, static fn ($key) => !is_int($key), ARRAY_FILTER_USE_KEY);
            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class, $action] = $handler;
                $instance = new $class();
                $instance->$action($request, $params);
                return;
            }
            $handler($request, $params);
            return;
        }

        jsonResponse(['ok' => false, 'message' => 'Rota não encontrada.'], 404);
    }
}
