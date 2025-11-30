<?php

namespace Framework\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface as PsrMiddleware;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Fully PSR-15 compatible middleware interface.
 *
 * This simply aliases the real PSR interface so your framework
 * can import it from a consistent namespace.
 */
interface MiddlewareInterface extends PsrMiddleware
{
    /**
     * Process an incoming request and return a response.
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface;
}
