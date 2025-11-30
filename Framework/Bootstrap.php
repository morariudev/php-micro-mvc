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

/**
 * Class Bootstrap
 *
 * Handles the initialization of the framework:
 * - Loads config
 * - Registers core services (DB, Twig, Session, Events)
 * - Loads routes (with optional route caching)
 * - Sets up the dispatcher and middleware
 */
class Bootstrap
{
    private string $basePath;
    private Container $container;
    private Router $router;

    public function __construct(string $basePath)
    {
        $this->basePath  = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->container = new Container();
        $this->router    = new Router();
    }

    /**
     * Initialize framework and return the HTTP Dispatcher.
     */
    public function init(): Dispatcher
    {
        $this->loadConfig();
        $this->registerCoreServices();
        $this->loadRoutes();

        $dispatcher = new Dispatcher($this->router, $this->container);

        // Middleware stack order: auth → JSON body → CSRF → example
        $dispatcher->addMiddleware(SessionAuthMiddleware::class);
        $dispatcher->addMiddleware(\Framework\Middleware\JsonBodyMiddleware::class);
        $dispatcher->addMiddleware(CsrfMiddleware::class);
        $dispatcher->addMiddleware(ExampleMiddleware::class);

        return $dispatcher;
    }

    /**
     * Load configuration files into the container.
     */
    private function loadConfig(): void
    {
        $app = require $this->basePath . '/App/Config/app.php';
        $db  = require $this->basePath . '/App/Config/database.php';

        $this->container->set('config.app', $app);
        $this->container->set('config.database', $db);
    }

    /**
     * Register core framework services.
     */
    private function registerCoreServices(): void
    {
        $appConfig = $this->container->get('config.app');
        $debug     = (bool)($appConfig['debug'] ?? true);

        // Database singleton
        $this->container->set(Database::class, function (): Database {
            return new Database($this->container->get('config.database'));
        });

        // Twig singleton (pass session + debug flag)
        $this->container->set(TwigRenderer::class, function () use ($debug): TwigRenderer {
            $session = $this->container->get(SessionManager::class);
            return new TwigRenderer($this->basePath . '/App/Views', $session, $debug);
        });

        // Session manager
        $this->container->set(SessionManager::class, function () {
            return new SessionManager();
        });

        // Event dispatcher
        $this->container->set(EventDispatcher::class, function () {
            return new EventDispatcher();
        });

        // Container & router access
        $this->container->set(Container::class, $this->container);
        $this->container->set(Router::class, $this->router);
    }

    /**
     * Load routes from files, optionally using route cache.
     */
    private function loadRoutes(): void
    {
        $routesPath = $this->basePath . '/App/Routes';
        $cacheFile  = $this->basePath . '/cache/routes.cache.php';
        $cache      = new RouteCache($cacheFile);

        $appConfig = $this->container->get('config.app');
        $debug     = (bool)($appConfig['debug'] ?? false);

        // Use cached routes in non-debug mode if available
        if (!$debug) {
            $cachedRoutes = $cache->read();
            if ($cachedRoutes !== null) {
                $this->router->setRoutes($cachedRoutes);
                return;
            }
        }

        // Load routes from files
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

        // Save route cache in non-debug mode if all routes are serializable
        if (!$debug && $this->routesAreCacheable()) {
            $cache->write($this->router->getRoutes());
        }
    }

    /**
     * Returns true if all route handlers are serializable (no closures).
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

    /**
     * Return DI container instance.
     */
    public function getContainer(): Container
    {
        return $this->container;
    }
}
