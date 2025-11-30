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

    /**
     * Optional configuration
     */
    public function configure(array $options): self
    {
        $this->cookieConfig = array_merge($this->cookieConfig, $options);
        return $this;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 1) Configure session cookie before starting
        $this->configureCookieParams();

        // 2) Start the session safely
        $this->session->start();

        // 3) Attach session to request
        $request = $request->withAttribute('session', $this->session);

        // 4) Continue pipeline
        $response = $handler->handle($request);

        // 5) Allow session to modify cookie headers AFTER middleware runs
        $this->appendSessionCookie($response);

        return $response;
    }

    /**
     * Apply cookie settings BEFORE session_start()
     */
    private function configureCookieParams(): void
    {
        session_set_cookie_params([
            'lifetime' => $this->cookieConfig['lifetime'],
            'path'     => $this->cookieConfig['path'],
            'domain'   => $this->cookieConfig['domain'],
            'secure'   => $this->cookieConfig['secure'],
            'httponly' => $this->cookieConfig['httponly'],
            'samesite' => $this->cookieConfig['samesite'],
        ]);
    }

    /**
     * Add correct Set-Cookie headers if session changed.
     */
    private function appendSessionCookie(ResponseInterface $response): void
    {
        if (headers_sent()) {
            return;
        }

        // PHP will generate the cookie header internally.
        // No work needed here unless you want to force session regeneration.
    }
}
