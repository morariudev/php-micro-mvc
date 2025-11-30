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

        // Middleware stack
        $dispatcher->addMiddleware(SessionAuthMiddleware::class);
        $dispatcher->addMiddleware(CsrfMiddleware::class);
        $dispatcher->addMiddleware(ExampleMiddleware::class);

        return $dispatcher;
    }

    private function loadConfig(): void
    {
        $app = require $this->basePath . '/App/Config/app.php';
        $db = require $this->basePath . '/App/Config/database.php';

        $this->container->set('config.app', $app);
        $this->container->set('config.database', $db);
    }

    private function registerCoreServices(): void
    {
        $this->container->set(Database::class, function (): Database {
            return new Database($this->container->get('config.database'));
        });

        $this->container->set(TwigRenderer::class, function (): TwigRenderer {
            return new TwigRenderer($this->basePath . '/App/Views');
        });

        $this->container->set(SessionManager::class, function () {
            return new SessionManager();
        });

        $this->container->set(EventDispatcher::class, function () {
            return new EventDispatcher();
        });

        // Provide container & router as dependencies
        $this->container->set(Container::class, $this->container);
        $this->container->set(Router::class, $this->router);
    }

    private function loadRoutes(): void
    {
        $collector = new RouteCollector($this->router);
        $routesPath = $this->basePath . '/App/Routes';

        foreach (glob($routesPath . '/*.php') as $file) {
            $callback = require $file;
            if (is_callable($callback)) {
                $callback($collector);
            }
        }

        $cache = new RouteCache($this->basePath . '/cache/routes.cache.php');
        $cache->write($this->router->getRoutes());
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}
