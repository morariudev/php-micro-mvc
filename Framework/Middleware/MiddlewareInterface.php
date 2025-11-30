<?php

namespace Framework\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface MiddlewareInterface
{
    /**
     * Process an incoming request.
     *
     * Compatible with PSR-15:
     *   - $next behaves like a RequestHandlerInterface::handle()
     *
     * @param callable(ServerRequestInterface):ResponseInterface $next
     */
    public function process(ServerRequestInterface $request, callable $next): ResponseInterface;
}
