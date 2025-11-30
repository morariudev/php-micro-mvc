<?php

namespace Framework\Router;

class Router
{
    /** @var array<int, Route> */
    private array $routes = [];

    /**
     * Compiled route tree:
     * [
     *   'children' => [
     *       'segment' => [
     *           'children' => [...],
     *           'routes'   => [Route, ...]
     *       ],
     *       ...
     *   ],
     *   'routes' => [Route, ...]   // Routes for "/"
     * ]
     *
     * @var array<string, mixed>
     */
    private array $compiledTree = [
        'children' => [],
        'routes'   => [],
    ];

    private bool $compiled = false;

    public function __construct()
    {
        $this->resetCompiledTree();
    }

    /**
     * Register a new route.
     *
     * @param callable|array|string $handler
     */
    public function add(string $method, string $path, $handler): Route
    {
        $method = strtoupper($method);
        $path   = $this->normalizePath($path);

        $route = new Route($method, $path, $handler);

        // Prevent accidental duplicates in development
        $this->guardAgainstDuplicateRoute($route);

        $this->routes[] = $route;

        // Mark compiled tree dirty
        $this->compiled = false;

        return $route;
    }

    /**
     * @return array<int, Route>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Used by RouteCache + Bootstrap to load cached routes.
     *
     * @param array<int, Route> $routes
     */
    public function setRoutes(array $routes): void
    {
        $this->routes   = $routes;
        $this->compiled = false;
    }

    /**
     * Reset the compiled route tree.
     */
    private function resetCompiledTree(): void
    {
        $this->compiledTree = [
            'children' => [],
            'routes'   => [],
        ];
        $this->compiled = false;
    }

    /**
     * Normalize and validate route paths.
     *
     * - Always starts with "/"
     * - Trim trailing slash unless it's the root
     */
    private function normalizePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '/';
        }

        $path = '/' . ltrim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /**
     * Throw an exception if a duplicate method + path is added.
     */
    private function guardAgainstDuplicateRoute(Route $route): void
    {
        foreach ($this->routes as $existing) {
            if (
                $existing->getMethod() === $route->getMethod() &&
                $existing->getPath() === $route->getPath()
            ) {
                // This is only a warning-level issue but very helpful for debugging.
                throw new \RuntimeException(
                    sprintf(
                        'Duplicate route detected: [%s] %s',
                        $route->getMethod(),
                        $route->getPath()
                    )
                );
            }
        }
    }

    /**
     * Build a fast-matching route tree based on path segments.
     * Dynamic segments remain in raw form: "{id}" or "{slug:[a-z]+}".
     */
    public function compile(): void
    {
        if ($this->compiled) {
            return;
        }

        $this->resetCompiledTree();

        foreach ($this->routes as $route) {
            $path     = $route->getPath();
            $segments = $path === '/' ? [] : explode('/', trim($path, '/'));

            $node =& $this->compiledTree;

            foreach ($segments as $seg) {
                if (!isset($node['children'][$seg])) {
                    $node['children'][$seg] = [
                        'children' => [],
                        'routes'   => [],
                    ];
                }

                // Move deeper into the tree
                $node =& $node['children'][$seg];
            }

            // Attach route to leaf node
            $node['routes'][] = $route;
        }

        $this->compiled = true;
    }

    /**
     * Get the compiled route tree, compiling if needed.
     *
     * @return array<string, mixed>
     */
    public function getCompiledTree(): array
    {
        if (!$this->compiled) {
            $this->compile();
        }

        return $this->compiledTree;
    }
}
