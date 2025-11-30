<?php

namespace Framework\Http;

use Framework\Support\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class Controller
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Resolve a service from the container.
     */
    protected function resolve(string $id): mixed
    {
        return $this->container->get($id);
    }

    /**
     * Render a Twig view.
     */
    protected function view(string $template, array $data = []): ResponseInterface
    {
        return \view($template, $data);
    }

    /**
     * JSON response.
     */
    protected function json(mixed $data, int $status = 200): ResponseInterface
    {
        return \json($data, $status);
    }

    /**
     * Redirect response.
     */
    protected function redirect(string $url, int $status = 302): ResponseInterface
    {
        return \redirect($url, $status);
    }

    /**
     * Read input from:
     *  - JSON body
     *  - form body
     *  - query params
     *  - route params
     */
    protected function input(ServerRequestInterface $request, string $key, mixed $default = null): mixed
    {
        // JSON or form body
        $body = $request->getParsedBody();
        if (is_array($body) && array_key_exists($key, $body)) {
            return $body[$key];
        }

        // Query string
        $query = $request->getQueryParams();
        if (array_key_exists($key, $query)) {
            return $query[$key];
        }

        // Route parameters
        return $request->getAttribute($key, $default);
    }
}
