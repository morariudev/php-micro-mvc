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
     * Define a named middleware group.
     *
     * @param array<int, string> $middleware
     */
    public function defineGroup(string $name, array $middleware): void
    {
        $this->middlewareGroups[$name] = $middleware;
    }

    /**
     * Use a named middleware group for all routes in the callback.
     *
     * @param callable(RouteCollector):void $callback
     */
    public function useGroup(string $name, callable $callback): void
    {
        $middleware = $this->middlewareGroups[$name] ?? [];
        $this->group('', $callback, $middleware);
    }

    /**
     * @param callable(RouteCollector):void $callback
     * @param array<int, string> $middleware
     */
    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $prefix = trim($prefix, '/');
        $this->groupPrefix = $previousPrefix;

        if ($prefix !== '') {
            $this->groupPrefix = rtrim($previousPrefix . '/' . $prefix, '/');
        }

        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /**
     * @param callable|array|string $handler
     */
    public function add(string $method, string $path, $handler, array $middleware = []): Route
    {
        $path = trim($path, '/');

        $prefix = trim($this->groupPrefix, '/');
        if ($prefix !== '') {
            $fullPath = '/' . trim($prefix . '/' . $path, '/');
        } else {
            $fullPath = $path === '' ? '/' : '/' . $path;
        }

        $fullPath = $fullPath === '' ? '/' : $fullPath;
        $fullPath = $fullPath === '/' ? '/' : rtrim($fullPath, '/');

        $route = $this->router->add($method, $fullPath, $handler);
        $route->addMiddleware(array_merge($this->groupMiddleware, $middleware));

        return $route;
    }

    /**
     * @param callable|array|string $handler
     */
    public function get(string $path, $handler, array $middleware = []): Route
    {
        return $this->add('GET', $path, $handler, $middleware);
    }

    /**
     * @param callable|array|string $handler
     */
    public function post(string $path, $handler, array $middleware = []): Route
    {
        return $this->add('POST', $path, $handler, $middleware);
    }

    /**
     * @param callable|array|string $handler
     */
    public function put(string $path, $handler, array $middleware = []): Route
    {
        return $this->add('PUT', $path, $handler, $middleware);
    }

    /**
     * @param callable|array|string $handler
     */
    public function delete(string $path, $handler, array $middleware = []): Route
    {
        return $this->add('DELETE', $path, $handler, $middleware);
    }

    /**
     * @param callable|array|string $handler
     */
    public function patch(string $path, $handler, array $middleware = []): Route
    {
        return $this->add('PATCH', $path, $handler, $middleware);
    }
}
