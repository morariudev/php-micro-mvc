<?php

namespace Framework\Router;

class Router
{
    /** @var array<int, Route> */
    private array $routes = [];

    /**
     * Compiled route tree:
     * [
     *   'children' => [ 'segment' => [ 'children' => [...], 'routes' => [Route, ...] ], ... ],
     *   'routes'   => [Route, ...] // routes for "/"
     * ]
     *
     * @var array<string, mixed>
     */
    private array $compiledTree = [
        'children' => [],
        'routes' => [],
    ];

    private bool $compiled = false;

    public function __construct()
    {
        $this->resetCompiledTree();
    }

    /**
     * @param callable|array|string $handler
     */
    public function add(string $method, string $path, $handler): Route
    {
        $route = new Route($method, $path, $handler);
        $this->routes[] = $route;

        // any change invalidates the compiled tree
        $this->compiled = false;

        return $route;
    }

    /** @return array<int, Route> */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    private function resetCompiledTree(): void
    {
        $this->compiledTree = [
            'children' => [],
            'routes' => [],
        ];
        $this->compiled = false;
    }

    /**
     * Build a simple tree based on path segments.
     * Root node ("/") is in $this->compiledTree itself.
     */
    public function compile(): void
    {
        if ($this->compiled) {
            return;
        }

        $this->resetCompiledTree();

        foreach ($this->routes as $route) {
            $path = $route->getPath();
            $segments = $path === '/' ? [] : explode('/', trim($path, '/'));

            $node =& $this->compiledTree;

            foreach ($segments as $seg) {
                if (!isset($node['children'][$seg])) {
                    $node['children'][$seg] = [
                        'children' => [],
                        'routes' => [],
                    ];
                }

                $node =& $node['children'][$seg];
            }

            $node['routes'][] = $route;
        }

        $this->compiled = true;
    }

    /**
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
