<?php

namespace Framework\Middleware;

use Framework\Session\SessionManager;
use Framework\Support\Container;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SessionAuthMiddleware implements MiddlewareInterface
{
    private SessionManager $session;
    private Container $container;

    /**
     * Optional settings:
     *
     *   - protectedPaths: areas requiring login
     *   - guestPaths: paths that require the visitor to be *not* logged in
     *   - loginRedirect: redirect to this URL if not logged in
     */
    private array $protectedPaths = [];
    private array $guestPaths = [];
    private ?string $loginRedirect = null;

    public function __construct(SessionManager $session, Container $container)
    {
        $this->session = $session;
        $this->container = $container;
    }

    /**
     * Optional: configure middleware externally
     */
    public function protectPaths(array $paths, ?string $redirect = null): void
    {
        $this->protectedPaths = $paths;
        $this->loginRedirect = $redirect;
    }

    public function guestOnly(array $paths): void
    {
        $this->guestPaths = $paths;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $this->session->start();

        // Attach user info
        $userId = $this->session->get('user_id');

        if ($userId !== null) {
            // Store user_id
            $request = $request->withAttribute('user_id', $userId);

            // Try resolving a User model automatically if it exists
            if (class_exists(\App\Models\User::class)) {
                $userModel = $this->container->get(\App\Models\User::class);
                $user = $userModel->find($userId);
                if ($user !== null) {
                    $request = $request->withAttribute('user', $user);
                }
            }
        }

        $path = rtrim($request->getUri()->getPath(), '/') ?: '/';

        // ---------------------------------------------------------------------
        // 1) Protect secure paths (must be authenticated)
        // ---------------------------------------------------------------------
        if (!empty($this->protectedPaths) && $this->matchesAny($path, $this->protectedPaths)) {
            if ($userId === null) {

                // If JSON API → return 401 JSON instead of redirect
                if (str_starts_with($path, '/api')) {
                    $factory = new Psr17Factory();
                    $response = $factory->createResponse(401);
                    $response->getBody()->write(json_encode([
                        'error' => 'Authentication required'
                    ]));
                    return $response->withHeader('Content-Type', 'application/json');
                }

                // Redirect user to login page
                if ($this->loginRedirect) {
                    return redirect($this->loginRedirect);
                }

                // Default unauthorized response
                $factory = new Psr17Factory();
                $response = $factory->createResponse(401);
                $response->getBody()->write('Unauthorized.');
                return $response;
            }
        }

        // ---------------------------------------------------------------------
        // 2) Guest-only paths (cannot be authenticated)
        // ---------------------------------------------------------------------
        if (!empty($this->guestPaths) && $this->matchesAny($path, $this->guestPaths)) {
            if ($userId !== null) {
                return redirect('/'); // Default: send logged-in users home
            }
        }

        // Continue pipeline
        return $next($request);
    }

    /**
     * Basic wildcard path matching:
     *   "/admin/*" matches "/admin/users/1"
     */
    private function matchesAny(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';
            if (preg_match($regex, $path)) {
                return true;
            }
        }
        return false;
    }
}
