<?php

namespace App\Http\Middleware;

use Framework\Middleware\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ExampleMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        /**
         * -----------------------------------------------------
         * Example: modify the incoming request.
         * You can attach attributes here, e.g.:
         *
         *   $request = $request->withAttribute('foo', 'bar');
         *
         * The next middleware/controller will have access to:
         *   $request->getAttribute('foo');
         * -----------------------------------------------------
         */

        // Continue to the next middleware/controller
        $response = $next($request);

        /**
         * -----------------------------------------------------
         * Example: modify the outgoing response.
         * This is useful for:
         *   - adding security headers
         *   - adding debug headers
         *   - response caching logic
         * -----------------------------------------------------
         */
        return $response->withHeader('X-Example-Middleware', 'executed');
    }
}
