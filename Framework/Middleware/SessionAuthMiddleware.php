<?php

namespace Framework\Middleware;

use Framework\Session\SessionManager;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SessionAuthMiddleware implements MiddlewareInterface
{
    private SessionManager $session;

    public function __construct(SessionManager $session)
    {
        $this->session = $session;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $this->session->start();

        // Very simple example: attach user_id from session as request attribute
        $userId = $this->session->get('user_id');
        if ($userId !== null) {
            $request = $request->withAttribute('user_id', $userId);
        }

        // You could restrict access to certain paths here and redirect if not authed
        // For now, just continue.
        return $next($request);
    }
}
