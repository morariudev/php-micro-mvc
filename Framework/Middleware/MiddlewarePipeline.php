<?php

namespace Framework\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MiddlewarePipeline implements RequestHandlerInterface
{
    /** @var MiddlewareInterface[] */
    private array $middleware = [];

    private RequestHandlerInterface $finalHandler;
    private int $index = 0;

    public function __construct(RequestHandlerInterface $finalHandler)
    {
        $this->finalHandler = $finalHandler;
    }

    public function add(MiddlewareInterface $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // If no middleware left → run the final route handler
        if (!isset($this->middleware[$this->index])) {
            return $this->finalHandler->handle($request);
        }

        // Fetch current middleware and increment index
        $middleware = $this->middleware[$this->index];
        $this->index++;

        // Execute middleware
        return $middleware->process($request, $this);
    }
}
