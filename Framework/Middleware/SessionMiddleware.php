<?php

namespace Framework\Middleware;

use Framework\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SessionMiddleware implements MiddlewareInterface
{
    private SessionManager $session;

    private array $cookieConfig = [
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',  // None / Lax / Strict
    ];

    public function __construct(SessionManager $session)
    {
        $this->session = $session;
    }

    public function configure(array $options): self
    {
        $this->cookieConfig = array_merge($this->cookieConfig, $options);
        return $this;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->configureCookieParams();
        $this->session->start();

        // Attach session to request
        $request = $request->withAttribute('session', $this->session);

        $response = $handler->handle($request);

        // No-op; PHP handles session cookie
        return $response;
    }

    private function configureCookieParams(): void
    {
        session_set_cookie_params([
            'lifetime' => $this->cookieConfig['lifetime'],
            'path'     => $this->cookieConfig['path'],
            'domain'   => $this->cookieConfig['domain'],
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => $this->cookieConfig['httponly'],
            'samesite' => $this->cookieConfig['samesite'],
        ]);
    }
}
