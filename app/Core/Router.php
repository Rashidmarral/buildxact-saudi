<?php

namespace App\Core;

class Router
{
    private array $routes = [];
    private array $middlewareStack = [];

    public function get(string $path, $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function group(array $middleware, callable $callback): void
    {
        $this->middlewareStack = array_merge($this->middlewareStack, $middleware);
        $callback($this);
        $this->middlewareStack = array_slice($this->middlewareStack, 0, -count($middleware));
    }

    private function add(string $method, string $path, $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $this->middlewareStack,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/');
        if ($uri === '') {
            $uri = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $pattern = preg_replace('#\{[a-zA-Z_]+\}#', '([^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);

                foreach ($route['middleware'] as $mw) {
                    $mw();
                }

                [$class, $action] = $route['handler'];
                $controller = new $class();
                call_user_func_array([$controller, $action], $matches);
                return;
            }
        }

        http_response_code(404);
        View::render('errors/404', []);
    }
}
