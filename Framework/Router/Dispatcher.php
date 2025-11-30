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
        // Normalize path: remove trailing slash except for root
        $path = rtrim($request->getUri()->getPath(), '/') ?: '/';
        $originalMethod = strtoupper($request->getMethod());
        $method = $originalMethod;
        $isHeadRequest = $originalMethod === 'HEAD';

        // Ensure route tree is compiled
        $this->router->compile();

        try {
            // Automatic OPTIONS handling (CORS preflight)
            if ($method === 'OPTIONS') {
                $optionsResponse = $this->handleOptions($path);
                if ($optionsResponse !== null) {
                    $optionsResponse = $this->withCors($optionsResponse);
                    return $this->stripBodyForHead($optionsResponse, $isHeadRequest);
                }
            }

            // Try to find matching route (HEAD tries GET route if no HEAD route)
            $params = [];
            $route = $this->matchCompiled($path, $method, $params);

            if ($route === null && $isHeadRequest) {
                // If no explicit HEAD route, fall back to GET route for same path
                $route = $this->matchCompiled($path, 'GET', $params);
            }

            if ($route !== null) {
                $handler = $route->getHandler();
                $middlewareStack = array_merge($this->globalMiddleware, $route->getMiddleware());

                $coreHandler = function (ServerRequestInterface $request) use ($handler, $params): ResponseInterface {
                    return $this->invokeHandler($handler, $request, $params);
                };

                $response = $this->buildMiddlewareStack($middlewareStack, $coreHandler)($request);

                $response = $this->withCors($response);
                return $this->stripBodyForHead($response, $isHeadRequest);
            }

            // No route found -> 404
            $response = $this->errorResponse(404, 'Not Found');
            $response = $this->withCors($response);

            return $this->stripBodyForHead($response, $isHeadRequest);
        } catch (Throwable $e) {
            // Unexpected error -> 500
            $response = $this->errorResponse(500, $e);
            $response = $this->withCors($response);

            return $this->stripBodyForHead($response, $isHeadRequest);
        }
    }

    /**
     * Apply CORS headers to a response.
     */
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

    /**
     * Strip body for HEAD requests as required by HTTP spec.
     */
    private function stripBodyForHead(ResponseInterface $response, bool $isHeadRequest): ResponseInterface
    {
        if (!$isHeadRequest) {
            return $response;
        }

        $factory = new Psr17Factory();
        $emptyStream = $factory->createStream('');

        return $response->withBody($emptyStream);
    }

    /**
     * Handle automatic OPTIONS preflight.
     */
    private function handleOptions(string $path): ?ResponseInterface
    {
        $allowed = [];

        foreach ($this->router->getRoutes() as $route) {
            $params = [];
            if ($this->match($route->getPath(), $path, $params)) {
                $allowed[] = strtoupper($route->getMethod());
            }
        }

        $allowed = array_values(array_unique($allowed));

        // HEAD is automatically allowed if GET is allowed
        if (in_array('GET', $allowed, true) && !in_array('HEAD', $allowed, true)) {
            $allowed[] = 'HEAD';
        }

        if (empty($allowed)) {
            return null;
        }

        $factory = new Psr17Factory();
        $response = $factory->createResponse(204);

        return $response
            ->withHeader('Allow', implode(', ', $allowed))
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $allowed))
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }

    /**
     * Build middleware pipeline.
     *
     * @param array<int, string> $middlewareStack
     * @param callable(ServerRequestInterface):ResponseInterface $coreHandler
     * @return callable(ServerRequestInterface):ResponseInterface
     */
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

    /**
     * @param callable|array|string $handler
     * @param array<string, string|int> $params
     */
    private function invokeHandler($handler, ServerRequestInterface $request, array $params): ResponseInterface
    {
        if (is_array($handler) && count($handler) === 2) {
            $class = $handler[0];
            $method = $handler[1];

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
        $response->getBody()->write('Invalid route handler');

        return $response;
    }

    /**
     * Fast route matching using compiled tree, with support for constraints.
     *
     * Route patterns:
     *  - /user/{id}
     *  - /post/{slug:[a-z0-9\-]+}
     *
     * @param array<string, string|int> $params
     */
    private function matchCompiled(string $path, string $method, array &$params): ?Route
    {
        $tree = $this->router->getCompiledTree();
        $segments = $path === '/' ? [] : explode('/', trim($path, '/'));

        $node = $tree;
        $params = [];

        foreach ($segments as $seg) {
            // 1) Exact static match
            if (isset($node['children'][$seg])) {
                $node = $node['children'][$seg];
                continue;
            }

            // 2) Parameterized matches with optional constraints
            $matched = false;

            foreach ($node['children'] as $key => $child) {
                if (preg_match('/^{([a-zA-Z_][a-zA-Z0-9_]*)(?::(.+))?}$/', $key, $matches)) {
                    $name = $matches[1];
                    $pattern = $matches[2] ?? null;

                    if ($pattern !== null && preg_match('#^' . $pattern . '$#', $seg) !== 1) {
                        continue;
                    }

                    $params[$name] = $seg;
                    $node = $child;
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                return null;
            }
        }

        if (!empty($node['routes'])) {
            /** @var Route $route */
            foreach ($node['routes'] as $route) {
                if ($route->getMethod() === $method) {
                    return $route;
                }
            }
        }

        return null;
    }

    /**
     * Legacy matcher used for things like OPTIONS method discovery,
     * now also supports constraints {id:\d+}.
     *
     * @param array<string, string|int> $params
     */
    private function match(string $routePath, string $requestPath, array &$params): bool
    {
        $routeParts = explode('/', trim($routePath, '/'));
        $requestParts = explode('/', trim($requestPath, '/'));

        if (count($routeParts) !== count($requestParts)) {
            return false;
        }

        $params = [];

        foreach ($routeParts as $index => $part) {
            // Parameterized segments: {id} or {id:\d+}
            if (preg_match('/^{([a-zA-Z_][a-zA-Z0-9_]*)(?::(.+))?}$/', $part, $matches)) {
                $name = $matches[1];
                $pattern = $matches[2] ?? null;
                $value = $requestParts[$index];

                if ($pattern !== null && preg_match('#^' . $pattern . '$#', $value) !== 1) {
                    return false;
                }

                $params[$name] = $value;
                continue;
            }

            // Static segments
            if ($part !== $requestParts[$index]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build custom error responses. Uses Twig if available:
     *  - App/Views/errors/404.twig
     *  - App/Views/errors/500.twig
     *
     * Falls back to plain text if templates are missing.
     *
     * @param int $status
     * @param string|Throwable $error
     */
    private function errorResponse(int $status, $error): ResponseInterface
    {
        $factory = new Psr17Factory();

        // Determine debug mode from config, if available
        $debug = false;
        try {
            $config = $this->container->get('config.app');
            if (is_array($config) && array_key_exists('debug', $config)) {
                $debug = (bool) $config['debug'];
            }
        } catch (Throwable $e) {
            // ignore, default debug = false
        }

        $message = '';

        if ($status === 404) {
            $message = 'Page not found';
        } elseif ($status === 500) {
            if ($error instanceof Throwable) {
                $message = $debug ? $error->getMessage() : 'Internal Server Error';
            } else {
                $message = 'Internal Server Error';
            }
        } else {
            $message = is_string($error) ? $error : 'Error';
        }

        // Try Twig-based error page if Twig is registered
        if (class_exists(TwigRenderer::class) && $this->container->has(TwigRenderer::class)) {
            try {
                /** @var TwigRenderer $view */
                $view = $this->container->get(TwigRenderer::class);

                $template = 'errors/' . $status . '.twig';
                $data = [
                    'status' => $status,
                    'message' => $message,
                    'debug' => $debug,
                    'exception' => $error instanceof Throwable && $debug ? $error : null,
                ];

                $response = $view->render($template, $data)->withStatus($status);

                return $response;
            } catch (Throwable $e) {
                // if Twig fails (missing template, etc.), fall back to plain text
            }
        }

        // Plain text fallback
        $response = $factory->createResponse($status);
        $response->getBody()->write($message);

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
