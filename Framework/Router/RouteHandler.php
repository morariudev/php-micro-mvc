<?php

namespace Framework\Router;

use Framework\Support\Container;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RouteHandler implements RequestHandlerInterface
{
    private $handler;
    private array $params;
    private Container $container;

    public function __construct($handler, array $params, Container $container)
    {
        $this->handler   = $handler;
        $this->params    = $params;
        $this->container = $container;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handler = $this->handler;

        // Controller style: [Class, method]
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = $this->container->get($class);

            return $controller->$method($request, ...array_values($this->params));
        }

        // Closures
        if (is_callable($handler)) {
            return $handler($request, $this->params);
        }

        // String function
        if (is_string($handler) && function_exists($handler)) {
            return $handler($request, $this->params);
        }

        // Fallback
        $factory = new Psr17Factory();
        $response = $factory->createResponse(500);
        $response->getBody()->write("Invalid route handler");
        return $response;
    }
}
