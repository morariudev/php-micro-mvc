<?php

namespace Framework\Router;

use Framework\Middleware\MiddlewareInterface;
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

    /** @var array<int, string> */
    private array $globalMiddleware = [];

    public function __construct(Router $router, Container $container)
    {
        $this->router = $router;
        $this->container = $container;
    }

    public function addMiddleware(string $middlewareClass): void
    {
        $this->globalMiddleware[] = $middlewareClass;
    }

    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $path = rtrim($request->getUri()->getPath(), '/') ?: '/';
        $originalMethod = strtoupper($request->getMethod());
        $method = $originalMethod;
        $isHeadRequest = $method === 'HEAD';

        $this->router->compile();

        try {
            if ($method === 'OPTIONS') {
                $optionsResponse = $this->handleOptions($path);

                if ($optionsResponse !== null) {
                    return $this->stripBodyForHead($this->withCors($optionsResponse), $isHeadRequest);
                }
            }

            $params = [];
            $route = $this->matchCompiled($path, $method, $params);

            if ($route === null && $isHeadRequest) {
                $route = $this->matchCompiled($path, 'GET', $params);
            }

            if ($route !== null) {
                $handler = $route->getHandler();
                $middlewareStack = array_merge($this->globalMiddleware, $route->getMiddleware());

                $coreHandler = function (ServerRequestInterface $req) use ($handler, $params): ResponseInterface {
                    return $this->invokeHandler($handler, $req, $params);
                };

                $response = $this->buildMiddlewareStack($middlewareStack, $coreHandler)($request);

                return $this->stripBodyForHead($this->withCors($response), $isHeadRequest);
            }

            return $this->stripBodyForHead(
                $this->withCors($this->errorResponse(404, 'Not Found')),
                $isHeadRequest
            );

        } catch (Throwable $e) {
            return $this->stripBodyForHead(
                $this->withCors($this->errorResponse(500, $e)),
                $isHeadRequest
            );
        }
    }

    private function withCors(ResponseInterface $response): ResponseInterface
    {
        if (!$response->hasHeader('Access-Control-Allow-Origin')) {
            $response = $response->withHeader('Access-Control-Allow-Origin', '*');
        }

        if (!$response->hasHeader('Access-Control-Allow-Headers')) {
            $response = $response->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }

        if (!$response->hasHeader('Access-Control-Allow-Methods')) {
            $response = $response->withHeader(
                'Access-Control-Allow-Methods',
                'GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD'
            );
        }

        return $response;
    }

    private function stripBodyForHead(ResponseInterface $response, bool $isHeadRequest): ResponseInterface
    {
        if (!$isHeadRequest) {
            return $response;
        }

        $factory = new Psr17Factory();
        return $response->withBody($factory->createStream(''));
    }

    private function handleOptions(string $path): ?ResponseInterface
    {
        $allowed = [];

        foreach ($this->router->getRoutes() as $route) {
            $params = [];
            if ($this->match($route->getPath(), $path, $params)) {
                $allowed[] = strtoupper($route->getMethod());
            }
        }

        $allowed = array_unique($allowed);

        if (in_array('GET', $allowed, true) && !in_array('HEAD', $allowed, true)) {
            $allowed[] = 'HEAD';
        }

        if ($allowed === []) {
            return null;
        }

        $factory = new Psr17Factory();

        return $factory->createResponse(204)
            ->withHeader('Allow', implode(', ', $allowed))
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $allowed))
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }

    private function buildMiddlewareStack(array $middlewareStack, callable $coreHandler): callable
    {
        $next = $coreHandler;

        while ($middlewareClass = array_pop($middlewareStack)) {
            /** @var MiddlewareInterface $middleware */
            $middleware = $this->container->get($middlewareClass);

            $next = function (ServerRequestInterface $request) use ($middleware, $next): ResponseInterface {
                return $middleware->process($request, $next);
            };
        }

        return $next;
    }

    private function invokeHandler($handler, ServerRequestInterface $request, array $params): ResponseInterface
    {
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $controller = $this->container->get($class);

            $arguments = array_values($params);
            array_unshift($arguments, $request);

            return $controller->{$method}(...$arguments);
        }

        if (is_callable($handler)) {
            return $handler($request, $params);
        }

        if (is_string($handler) && function_exists($handler)) {
            return $handler($request, $params);
        }

        $factory = new Psr17Factory();
        $response = $factory->createResponse(500);
        $response->getBody()->write('Invalid route handler.');

        return $response;
    }

    /**
     * ----------------------------
     * FIXED VERSION OF matchCompiled()
     * ----------------------------
     */
    private function matchCompiled(string $path, string $method, array &$params): ?Route
    {
        $tree = $this->router->getCompiledTree();
        $segments = $path === '/' ? [] : explode('/', trim($path, '/'));

        $node = $tree;
        $params = [];

        foreach ($segments as $seg) {

            // Static match
            if (isset($node['children'][$seg])) {
                $node = $node['children'][$seg];
                continue;
            }

            // Dynamic segments
            $matched = false;
            $withPattern = [];
            $withoutPattern = [];

            foreach ($node['children'] as $key => $childNode) {

                if (preg_match('/^{([a-zA-Z_][a-zA-Z0-9_]*)(?::(.+))?}$/', $key, $m)) {

                    // FIX: safe detection of optional patterns
                    $hasPattern = isset($m[2]) && $m[2] !== '';

                    if ($hasPattern) {
                        $withPattern[] = [$key, $childNode, $m];
                    } else {
                        $withoutPattern[] = [$key, $childNode, $m];
                    }
                }
            }

            // Try pattern first → then no-pattern
            foreach (array_merge($withPattern, $withoutPattern) as [$key, $childNode, $m]) {
                $name = $m[1];
                $pattern = (isset($m[2]) && $m[2] !== '') ? $m[2] : null;

                if ($pattern && preg_match('#^' . $pattern . '$#', $seg) !== 1) {
                    continue;
                }

                $params[$name] = $seg;
                $node = $childNode;
                $matched = true;
                break;
            }

            if (!$matched) {
                return null;
            }
        }

        // Check for method match
        if (!empty($node['routes'])) {
            foreach ($node['routes'] as $route) {
                if ($route->getMethod() === $method) {
                    return $route;
                }
            }
        }

        return null;
    }

    private function match(string $routePath, string $requestPath, array &$params): bool
    {
        $routeParts = explode('/', trim($routePath, '/'));
        $reqParts   = explode('/', trim($requestPath, '/'));

        if (count($routeParts) !== count($reqParts)) {
            return false;
        }

        $params = [];

        foreach ($routeParts as $i => $part) {
            if (preg_match('/^{([a-zA-Z_][a-zA-Z0-9_]*)(?::(.+))?}$/', $part, $m)) {
                $pattern = $m[2] ?? null;
                $value = $reqParts[$i];

                if ($pattern && preg_match('#^' . $pattern . '$#', $value) !== 1) {
                    return false;
                }

                $params[$m[1]] = $value;
                continue;
            }

            if ($part !== $reqParts[$i]) {
                return false;
            }
        }

        return true;
    }

    private function errorResponse(int $status, $error): ResponseInterface
    {
        $factory = new Psr17Factory();
        $debug = false;

        try {
            $config = $this->container->get('config.app');
            $debug = is_array($config) && ($config['debug'] ?? false);
        } catch (Throwable $ignore) {}

        $message = match ($status) {
            404 => 'Page not found',
            500 => ($error instanceof Throwable && $debug)
                ? $error->getMessage()
                : 'Internal Server Error',
            default => is_string($error) ? $error : 'Error',
        };

        if (class_exists(TwigRenderer::class) && $this->container->has(TwigRenderer::class)) {
            try {
                /** @var TwigRenderer $view */
                $view = $this->container->get(TwigRenderer::class);

                return $view->render("errors/$status.twig", [
                    'status'    => $status,
                    'message'   => $message,
                    'debug'     => $debug,
                    'exception' => $debug && $error instanceof Throwable ? $error : null,
                ])->withStatus($status);

            } catch (Throwable $ignore) {}
        }

        $response = $factory->createResponse($status);
        $response->getBody()->write($message);

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
