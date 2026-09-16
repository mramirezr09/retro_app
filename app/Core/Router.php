<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $pattern, $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, $handler): void
    {
        $this->routes[] = [
            'method'  => $method,
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }
            $params = $this->match($route['pattern'], $request->path);
            if ($params === null) {
                continue;
            }
            $this->call($route['handler'], $params, $request);
            return;
        }

        Response::abort(404, 'Ruta no encontrada: ' . $request->path);
    }

    private function match(string $pattern, string $path): ?array
    {
        if ($pattern === $path) {
            return [];
        }

        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        if (!preg_match($regex, $path, $matches)) {
            return null;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (!is_int($key)) {
                $params[$key] = $value;
            }
        }
        return $params;
    }

    private function call($handler, array $params, Request $request): void
    {
        if (is_callable($handler)) {
            $handler($request, ...array_values($params));
            return;
        }

        [$class, $method] = explode('@', $handler);
        $class = 'App\\Controllers\\' . $class;
        if (!class_exists($class)) {
            Response::abort(500, 'Controlador no encontrado: ' . $class);
        }
        $controller = new $class($request);
        if (!method_exists($controller, $method)) {
            Response::abort(500, 'Metodo no encontrado: ' . $method);
        }
        $controller->{$method}(...array_values($params));
    }
}
