<?php

namespace Framework\Router;

class RouteGroup
{
    private string $prefix;
    /** @var array<int, string> */
    private array $middleware;
    private RouteCollector $collector;

    /**
     * @param array<int, string> $middleware
     */
    public function __construct(string $prefix, array $middleware, RouteCollector $collector)
    {
        $this->prefix = '/' . trim($prefix, '/');
        $this->middleware = $middleware;
        $this->collector = $collector;
    }

    /**
     * @param callable(RouteCollector):void $callback
     */
    public function group(callable $callback): void
    {
        $callback($this->collector);
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /** @return array<int, string> */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}
