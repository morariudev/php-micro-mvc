<?php

namespace Framework\Router;

use Framework\Router\Route;

class RouteCache
{
    private string $cacheFile;

    public function __construct(string $cacheFile)
    {
        $this->cacheFile = $cacheFile;
    }

    /**
     * @param array<int, Route> $routes
     */
    public function write(array $routes): void
    {
        $dir = \dirname($this->cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $export = serialize($routes);
        file_put_contents($this->cacheFile, $export);
    }

    /**
     * @return array<int, Route>|null
     */
    public function read(): ?array
    {
        if (!file_exists($this->cacheFile)) {
            return null;
        }

        $content = file_get_contents($this->cacheFile);
        if ($content === false) {
            return null;
        }

        $routes = @unserialize($content, [
            'allowed_classes' => [Route::class],
        ]);

        if (!is_array($routes)) {
            return null;
        }

        return $routes;
    }
}
