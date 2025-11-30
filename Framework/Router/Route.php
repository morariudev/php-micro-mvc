<?php

namespace Framework\Router;

class Route
{
    private string $method;
    private string $path;
    /** @var callable|array|string */
    private $handler;
    /** @var array<int, string> */
    private array $middleware;

    /**
     * @param callable|array|string $handler
     * @param array<int, string> $middleware
     */
    public function __construct(string $method, string $path, $handler, array $middleware = [])
    {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->handler = $handler;
        $this->middleware = $middleware;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /** @return callable|array|string */
    public function getHandler()
    {
        return $this->handler;
    }

    /** @return array<int, string> */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /** @param array<int, string> $middleware */
    public function addMiddleware(array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, $middleware);

        return $this;
    }
}
