<?php

namespace App\Http\Middleware;

use Framework\Middleware\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ExampleMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        // Example: add a custom header
        $response = $next($request);

        return $response->withHeader('X-Example-Middleware', 'executed');
    }
}
