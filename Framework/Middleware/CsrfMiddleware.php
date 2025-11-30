<?php

namespace Framework\Middleware;

use Framework\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Nyholm\Psr7\Factory\Psr17Factory;

class CsrfMiddleware implements MiddlewareInterface
{
    private SessionManager $session;

    public function __construct(SessionManager $session)
    {
        $this->session = $session;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->session->start();

        // Ensure token exists
        $token = $this->session->get('_csrf_token');
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            $this->session->set('_csrf_token', $token);
        }

        // Safe methods: GET, HEAD, OPTIONS
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $handler->handle(
                $request->withAttribute('csrf_token', $token)
            );
        }

        // Extract incoming token
        $parsed = $request->getParsedBody();
        $sentToken = is_array($parsed) ? ($parsed['_csrf'] ?? null) : null;

        // Or header token
        if ($request->hasHeader('X-CSRF-TOKEN')) {
            $sentToken = $request->getHeaderLine('X-CSRF-TOKEN');
        }

        if (!is_string($sentToken) || !hash_equals($token, $sentToken)) {
            return $this->failedResponse();
        }

        return $handler->handle(
            $request->withAttribute('csrf_token', $token)
        );
    }

    private function failedResponse(): ResponseInterface
    {
        $factory = new Psr17Factory();
        $response = $factory->createResponse(419);
        $response->getBody()->write('CSRF token mismatch.');
        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
