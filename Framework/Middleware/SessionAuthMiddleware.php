<?php

namespace Framework\Middleware;

use Framework\Session\SessionManager;
use Framework\Support\Container;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SessionAuthMiddleware implements MiddlewareInterface
{
    private SessionManager $session;
    private Container $container;

    private array $protectedPaths = [];
    private array $guestPaths = [];
    private ?string $loginRedirect = null;

    public function __construct(SessionManager $session, Container $container)
    {
        $this->session = $session;
        $this->container = $container;
    }

    // Optional configurators
    public function protectPaths(array $paths, ?string $redirect = null): self
    {
        $this->protectedPaths = $paths;
        $this->loginRedirect = $redirect;
        return $this;
    }

    public function guestOnly(array $paths): self
    {
        $this->guestPaths = $paths;
        return $this;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->session->start();

        // Attach user_id
        $userId = $this->session->get('user_id');
        if ($userId !== null) {
            $request = $request->withAttribute('user_id', $userId);

            // Auto-load user model (if exists)
            if (class_exists(\App\Models\User::class)) {
                $userModel = $this->container->get(\App\Models\User::class);
                $user = $userModel->find($userId);
                if ($user !== null) {
                    $request = $request->withAttribute('user', $user);
                }
            }
        }

        $path = rtrim($request->getUri()->getPath(), '/') ?: '/';

        // -------------------------
        // 1) Protected paths
        // -------------------------
        if ($this->matches($path, $this->protectedPaths)) {
            if ($userId === null) {
                return $this->unauthorizedResponse($path);
            }
        }

        // -------------------------
        // 2) Guest-only paths
        // -------------------------
        if ($userId !== null && $this->matches($path, $this->guestPaths)) {
            return $this->redirect('/');
        }

        return $handler->handle($request);
    }

    private function unauthorizedResponse(string $path): ResponseInterface
    {
        $factory = new Psr17Factory();

        // JSON API?
        if (str_starts_with($path, '/api')) {
            $resp = $factory->createResponse(401);
            $resp->getBody()->write(json_encode(['error' => 'Authentication required']));
            return $resp->withHeader('Content-Type', 'application/json');
        }

        // Redirect to login?
        if ($this->loginRedirect) {
            return $this->redirect($this->loginRedirect);
        }

        // Fallback plain 401
        $resp = $factory->createResponse(401);
        $resp->getBody()->write('Unauthorized.');
        return $resp;
    }

    private function redirect(string $to): ResponseInterface
    {
        $factory = new Psr17Factory();
        return $factory
            ->createResponse(302)
            ->withHeader('Location', $to);
    }

    private function matches(string $path, array $patterns): bool
    {
        foreach ($patterns as $p) {
            $pattern = '#^' . str_replace('\*', '.*', preg_quote($p, '#')) . '$#';
            if (preg_match($pattern, $path)) {
                return true;
            }
        }
        return false;
    }
}
