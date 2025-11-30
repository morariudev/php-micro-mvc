<?php

namespace Framework;

use App\Http\Middleware\ExampleMiddleware;
use Framework\Events\EventDispatcher;
use Framework\Middleware\CsrfMiddleware;
use Framework\Middleware\SessionAuthMiddleware;
use Framework\Router\Dispatcher;
use Framework\Router\RouteCache;
use Framework\Router\RouteCollector;
use Framework\Router\Router;
use Framework\Session\SessionManager;
use Framework\Support\Container;
use Framework\Support\Database;
use Framework\View\TwigRenderer;

class Bootstrap
{
    private string $basePath;
    private Container $container;
    private Router $router;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->container = new Container();
        $this->router = new Router();
    }

    public function init(): Dispatcher
    {
        $this->loadConfig();
        $this->registerCoreServices();
        $this->loadRoutes();

        $dispatcher = new Dispatcher($this->router, $this->container);

        // Middleware stack (order: auth → JSON → CSRF → example)
        $dispatcher->addMiddleware(SessionAuthMiddleware::class);
        $dispatcher->addMiddleware(\Framework\Middleware\JsonBodyMiddleware::class);
        $dispatcher->addMiddleware(CsrfMiddleware::class);
        $dispatcher->addMiddleware(ExampleMiddleware::class);

        return $dispatcher;
    }

    private function loadConfig(): void
    {
        $app = require $this->basePath . '/App/Config/app.php';
        $db  = require $this->basePath . '/App/Config/database.php';

        $this->container->set('config.app', $app);
        $this->container->set('config.database', $db);
    }

    private function registerCoreServices(): void
    {
        $appConfig = $this->container->get('config.app');
        $debug     = (bool)($appConfig['debug'] ?? true);

        // Database singleton
        $this->container->set(Database::class, function (): Database {
            return new Database($this->container->get('config.database'));
        });

        // TwigRenderer singleton with debug-aware caching
        $this->container->set(TwigRenderer::class, function () use ($debug): TwigRenderer {
            $cachePath = $debug ? null : $this->basePath . '/cache/twig';
            return new TwigRenderer($this->basePath . '/App/Views', $debug, $cachePath);
        });

        // SessionManager singleton
        $this->container->set(SessionManager::class, function () {
            return new SessionManager();
        });

        // EventDispatcher singleton
        $this->container->set(EventDispatcher::class, function () {
            return new EventDispatcher();
        });

        // Provide container & router as dependencies
        $this->container->set(Container::class, $this->container);
        $this->container->set(Router::class, $this->router);
    }

    private function loadRoutes(): void
    {
        $routesPath = $this->basePath . '/App/Routes';
        $cacheFile  = $this->basePath . '/cache/routes.cache.php';
        $cache      = new RouteCache($cacheFile);

        $appConfig = $this->container->get('config.app');
        $debug     = (bool)($appConfig['debug'] ?? false);

        // Load from cache if not in debug mode
        if (!$debug) {
            $cachedRoutes = $cache->read();
            if ($cachedRoutes !== null) {
                $this->router->setRoutes($cachedRoutes);
                return;
            }
        }

        $collector = new RouteCollector($this->router);

        foreach (glob($routesPath . '/*.php') as $file) {
            try {
                $callback = require $file;
                if (is_callable($callback)) {
                    $callback($collector);
                }
            } catch (\Throwable $e) {
                error_log("Failed to load route file $file: " . $e->getMessage());
            }
        }

        // Cache routes if cacheable and not in debug
        if (!$debug && $this->routesAreCacheable()) {
            $cache->write($this->router->getRoutes());
        }
    }

    /**
     * Ensure all route handlers are serializable (no Closures).
     */
    private function routesAreCacheable(): bool
    {
        foreach ($this->router->getRoutes() as $route) {
            $handler = $route->getHandler();
            if ($handler instanceof \Closure) {
                return false;
            }
        }
        return true;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}
