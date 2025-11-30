<?php

namespace App\Http\Middleware;

use Framework\Middleware\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ExampleMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Continue to next middleware
        $response = $handler->handle($request);

        // Add a header for debugging
        return $response->withHeader('X-Example-Middleware', 'executed');
    }
}
