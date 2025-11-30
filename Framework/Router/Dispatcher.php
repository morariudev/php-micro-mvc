<?php

namespace Framework\Router;

use Framework\Support\Container;
use Framework\View\TwigRenderer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class Dispatcher
{
    private Router $router;
    private Container $container;

    /** @var array<int,string> */
    private array $globalMiddleware = [];

    public function __construct(Router $router, Container $container)
    {
        $this->router = $router;
        $this->container = $container;
    }

    public function addMiddleware(string $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $method   = strtoupper($request->getMethod());
        $path     = rtrim($request->getUri()->getPath(), '/') ?: '/';

        try {
            // OPTIONS preflight
            if ($method === 'OPTIONS') {
                $resp = $this->handleOptions($path);
                return $resp ?? $this->jsonError(404, 'No routes for OPTIONS');
            }

            // HEAD fallback to GET inside Router::match()
            $params = [];
            $route = $this->router->match($method, $path, $params);

            if (!$route) {
                return $this->errorResponse(404, 'Not Found');
            }

            $handler = $route->getHandler();
            $middlewareStack = array_merge(
                $this->globalMiddleware,
                $route->getMiddleware()
            );

            $core = function (ServerRequestInterface $req) use ($handler, $params): ResponseInterface {
                return $this->invokeHandler($handler, $req, $params);
            };

            $pipeline = $this->buildPipeline($middlewareStack, $core);

            return $pipeline($request);

        } catch (Throwable $e) {
            return $this->errorResponse(500, $e);
        }
    }

    /**
     * ----------------------------
     * Middleware Pipeline
     * ----------------------------
     */
    private function buildPipeline(array $stack, callable $core): callable
    {
        $next = $core;

        while ($class = array_pop($stack)) {
            $middleware = $this->container->get($class);

            $next = function (ServerRequestInterface $req) use ($middleware, $next) {
                return $middleware->process($req, new class($next) implements \Psr\Http\Server\RequestHandlerInterface {
                    private $next;
                    public function __construct(callable $next) { $this->next = $next; }
                    public function handle(ServerRequestInterface $request): ResponseInterface {
                        return ($this->next)($request);
                    }
                });
            };
        }

        return $next;
    }

    /**
     * ----------------------------
     * Invoke Handler
     * ----------------------------
     */
    private function invokeHandler($handler, ServerRequestInterface $req, array $params): ResponseInterface
    {
        // Controller method: [Controller::class, 'method']
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = $this->container->get($class);
            return $controller->$method($req, ...array_values($params));
        }

        // Closures
        if (is_callable($handler)) {
            return $handler($req, $params);
        }

        // Function name
        if (is_string($handler) && function_exists($handler)) {
            return $handler($req, $params);
        }

        return $this->jsonError(500, 'Invalid route handler');
    }

    /**
     * ----------------------------
     * OPTIONS support
     * ----------------------------
     */
    private function handleOptions(string $path): ?ResponseInterface
    {
        $allowed = [];

        foreach ($this->router->getAllRoutesFlat() as $route) {
            $params = [];
            if ($this->router->match($route->getMethod(), $path, $params)) {
                $allowed[] = $route->getMethod();
            }
        }

        $allowed = array_unique($allowed);
        if (!$allowed) {
            return null;
        }

        $f = new Psr17Factory();
        return $f->createResponse(204)
            ->withHeader('Allow', implode(', ', $allowed))
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $allowed))
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }

    /**
     * ----------------------------
     * Error Response
     * ----------------------------
     */
    private function errorResponse(int $code, $error): ResponseInterface
    {
        $factory = new Psr17Factory();

        // Debug mode? Use message
        $message = ($error instanceof Throwable)
            ? $error->getMessage()
            : (string)$error;

        $response = $factory->createResponse($code);

        // Use Twig if available
        if ($this->container->has(TwigRenderer::class)) {
            try {
                /** @var TwigRenderer $view */
                $view = $this->container->get(TwigRenderer::class);
                return $view->render("errors/$code.twig", [
                    'status' => $code,
                    'message' => $message,
                    'exception' => $error instanceof Throwable ? $error : null,
                ])->withStatus($code);
            } catch (Throwable) {}
        }

        // Fallback
        $response->getBody()->write($message);
        return $response->withHeader('Content-Type', 'text/plain');
    }

    private function jsonError(int $code, string $msg): ResponseInterface
    {
        $factory = new Psr17Factory();
        $r = $factory->createResponse($code);
        $r->getBody()->write(json_encode(['error' => $msg]));
        return $r->withHeader('Content-Type', 'application/json');
    }
}
