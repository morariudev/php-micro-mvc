<?php

namespace Framework\Middleware;

use Framework\Session\SessionManager;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CsrfMiddleware implements MiddlewareInterface
{
    private SessionManager $session;

    public function __construct(SessionManager $session)
    {
        $this->session = $session;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $this->session->start();

        // 1. Always ensure a CSRF token exists
        if (!$this->session->get('_csrf_token')) {
            $this->session->set('_csrf_token', bin2hex(random_bytes(32)));
        }

        $token = $this->session->get('_csrf_token');

        // 2. For GET/HEAD/OPTIONS: Pass through + attach token attribute
        if (!in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request->withAttribute('csrf_token', $token));
        }

        // 3. For unsafe requests: validate CSRF token
        $parsed = $request->getParsedBody() ?? [];
        $sentToken = $parsed['_csrf'] ?? null;

        if (!is_string($sentToken) || !hash_equals($token, $sentToken)) {
            $factory = new Psr17Factory();
            $response = $factory->createResponse(419); // Laravel-style CSRF code
            $response->getBody()->write('CSRF token mismatch.');
            return $response;
        }

        return $next($request->withAttribute('csrf_token', $token));
    }
}
