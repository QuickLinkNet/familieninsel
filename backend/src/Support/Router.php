<?php

declare(strict_types=1);

namespace App\Support;

final class Router
{
    /**
     * @var array<string, array<int, array{pattern: string, paramNames: string[], handler: callable}>>
     */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, callable $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $paramNames = [];
        $pattern = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            function (array $matches) use (&$paramNames): string {
                $paramNames[] = $matches[1];

                return '([^/]+)';
            },
            $path,
        );

        $this->routes[$method][] = [
            'pattern' => '#^' . $pattern . '$#',
            'paramNames' => $paramNames,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['pattern'], $path, $matches) === 1) {
                $params = [];
                foreach ($route['paramNames'] as $index => $name) {
                    $params[$name] = $matches[$index + 1];
                }

                ($route['handler'])($params);

                return;
            }
        }

        JsonResponse::error(404, 'NOT_FOUND', 'Route wurde nicht gefunden.');
    }
}
