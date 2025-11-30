<?php

namespace Framework\Router;

class RouteCollector
{
    private Router $router;

    /** @var array<int, string> */
    private array $groupMiddleware = [];

    private string $groupPrefix = '';

    /** @var array<string, array<int, string>> */
    private array $middlewareGroups = [];

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Define a named middleware group ("auth", "api", etc.)
     */
    public function defineGroup(string $name, array $middleware): void
    {
        $this->middlewareGroups[$name] = $middleware;
    }

    /**
     * Use a named middleware group inside the callback
     */
    public function useGroup(string $name, callable $callback): void
    {
        $middleware = $this->middlewareGroups[$name] ?? [];
        $this->group('', $callback, $middleware);
    }

    /**
     * Route grouping (prefix + middleware)
     */
    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        // Normalize and merge prefixes
        $prefix = trim($prefix, '/');
        $merged = trim($previousPrefix, '/');

        if ($prefix !== '') {
            $merged = $merged !== '' ? $merged . '/' . $prefix : $prefix;
        }

        $this->groupPrefix = $merged;
        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);

        $callback($this);

        // Restore previous group settings
        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /**
     * Add a route with inherited group prefix + middleware
     */
    public function add(string $method, string $path, $handler, array $middleware = []): Route
    {
        $path = trim($path, '/');

        // Merge with group prefix
        $prefix = trim($this->groupPrefix, '/');
        if ($prefix !== '') {
            $fullPath = '/' . trim("$prefix/$path", '/');
        } else {
            $fullPath = $path === '' ? '/' : '/' . $path;
        }

        // Normalize the full path
        $fullPath = $fullPath === '' ? '/' : rtrim($fullPath, '/');

        // Register route
        $route = $this->router->add($method, $fullPath, $handler);

        // Apply middleware inheritance
        $route->addMiddleware(array_merge($this->groupMiddleware, $middleware));

        return $route;
    }

    // Convenience methods

    public function get(string $path, $handler, array $middleware = []): Route
    {
        return $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, $handler, array $middleware = []): Route
    {
        return $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, $handler, array $middleware = []): Route
    {
        return $this->add('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, $handler, array $middleware = []): Route
    {
        return $this->add('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, $handler, array $middleware = []): Route
    {
        return $this->add('DELETE', $path, $handler, $middleware);
    }
}
