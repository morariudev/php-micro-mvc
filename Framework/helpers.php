<?php

use Framework\Support\Container;
use Framework\View\TwigRenderer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

/**
 * -----------------------------------------------------
 * loadEnv — same as your existing implementation
 * -----------------------------------------------------
 */
if (!function_exists('loadEnv')) {
    function loadEnv(string $file): void
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

/**
 * env() — safe environment access
 */
if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        return $_ENV[$key] ?? getenv($key) ?? $default;
    }
}

/**
 * Global container accessor: app()
 * This gets injected from index.php
 */
if (!function_exists('app')) {
    function app(?string $id = null)
    {
        global $appContainer;
        if ($id === null) {
            return $appContainer;
        }
        return $appContainer->get($id);
    }
}

/**
 * redirect('/foo') — PSR-7 redirect response
 */
if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): ResponseInterface
    {
        $factory = new Psr17Factory();
        $response = $factory->createResponse($status);
        return $response->withHeader('Location', $url);
    }
}

/**
 * json(['name' => 'John']) — return JSON response
 */
if (!function_exists('json')) {
    function json($data, int $status = 200): ResponseInterface
    {
        $factory = new Psr17Factory();
        $response = $factory->createResponse($status);
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}

/**
 * view('home/index.twig', [...]) — Twig-rendered HTML response
 */
if (!function_exists('view')) {
    function view(string $template, array $data = []): ResponseInterface
    {
        /** @var TwigRenderer $twig */
        $twig = app(TwigRenderer::class);
        return $twig->render($template, $data);
    }
}
