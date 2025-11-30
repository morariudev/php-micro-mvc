<?php

namespace Framework\Router;

class Router
{
    /** @var array<string, array<int,Route>>  */
    private array $routes = [
        'GET'     => [],
        'POST'    => [],
        'PUT'     => [],
        'PATCH'   => [],
        'DELETE'  => [],
        'OPTIONS' => [],
        'HEAD'    => [],
    ];

    /**
     * Register a route.
     */
    public function add(string $method, string $path, $handler): Route
    {
        $method = strtoupper($method);
        $path   = $this->normalize($path);

        if (!isset($this->routes[$method])) {
            $this->routes[$method] = [];
        }

        $route = new Route($method, $path, $handler);

        $this->guardDuplicate($route);

        $this->routes[$method][] = $route;

        return $route;
    }

    /**
     * Shortcut helpers
     */
    public function get(string $path, $h): Route { return $this->add('GET', $path, $h); }
    public function post(string $path, $h): Route { return $this->add('POST', $path, $h); }
    public function put(string $path, $h): Route { return $this->add('PUT', $path, $h); }
    public function patch(string $path, $h): Route { return $this->add('PATCH', $path, $h); }
    public function delete(string $path, $h): Route { return $this->add('DELETE', $path, $h); }

    public function all(string $path, $h): void
    {
        foreach (['GET','POST','PUT','PATCH','DELETE','OPTIONS','HEAD'] as $method) {
            $this->add($method, $path, $h);
        }
    }

    /**
     * Normalize "/foo///bar/" → "/foo/bar"
     */
    private function normalize(string $path): string
    {
        $path = preg_replace('#/+#', '/', trim($path));
        $path = '/' . ltrim($path, '/');
        return rtrim($path, '/') ?: '/';
    }

    /**
     * Prevent duplicate method+path.
     */
    private function guardDuplicate(Route $route): void
    {
        foreach ($this->routes[$route->getMethod()] as $existing) {
            if ($existing->getPath() === $route->getPath()) {
                throw new \RuntimeException("Duplicate route [{$route->getMethod()}] {$route->getPath()}");
            }
        }
    }

    /**
     * Match a route by method+path.
     */
    public function match(string $method, string $path, array &$params = []): ?Route
    {
        $path = $this->normalize($path);
        $method = strtoupper($method);

        // HEAD fallback to GET
        $searchMethods = [$method];
        if ($method === 'HEAD') {
            $searchMethods[] = 'GET';
        }

        foreach ($searchMethods as $m) {
            if (!isset($this->routes[$m])) {
                continue;
            }

            foreach ($this->routes[$m] as $route) {
                if ($this->pathMatches($route->getPath(), $path, $params)) {
                    return $route;
                }
            }
        }

        return null;
    }

    /**
     * Match dynamic paths:
     *   /user/{id}
     *   /post/{slug:[a-z\-]+}
     */
    private function pathMatches(string $routePath, string $reqPath, array &$params): bool
    {
        if ($routePath === $reqPath) {
            $params = [];
            return true;
        }

        $routeParts = explode('/', trim($routePath, '/'));
        $urlParts   = explode('/', trim($reqPath, '/'));

        if (count($routeParts) !== count($urlParts)) {
            return false;
        }

        $params = [];

        foreach ($routeParts as $i => $part) {
            // Dynamic {name} or {name:regex}
            if (preg_match('/^{([a-zA-Z_][a-zA-Z0-9_]*)(?::(.+))?}$/', $part, $m)) {
                $name = $m[1];
                $regex = $m[2] ?? '[^/]+';

                if (!preg_match('#^' . $regex . '$#', $urlParts[$i])) {
                    return false;
                }

                $params[$name] = $urlParts[$i];
                continue;
            }

            // Static mismatch
            if ($part !== $urlParts[$i]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Exposed for debugging / route cache
     *
     * @return array<int,Route>
     */
    public function getAllRoutesFlat(): array
    {
        return array_merge(...array_values($this->routes));
    }

    /**
     * Used only by RouteCache to restore state.
     *
     * @param array<int,Route> $routes
     */
    public function setRoutes(array $routes): void
    {
        $this->routes = [
            'GET'     => [],
            'POST'    => [],
            'PUT'     => [],
            'PATCH'   => [],
            'DELETE'  => [],
            'OPTIONS' => [],
            'HEAD'    => [],
        ];

        foreach ($routes as $r) {
            $this->routes[$r->getMethod()][] = $r;
        }
    }
}
