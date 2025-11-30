<?php

use Framework\Bootstrap;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../Framework/helpers.php';

/**
 * --------------------------------------------------------
 * Environment Loading
 * --------------------------------------------------------
 * Docker Compose provides APP_ENV, APP_DEBUG, APP_URL.
 * The .env file is loaded ONLY as a fallback override.
 *
 * - In production: .env is read because you included it
 *   with `env_file: - .env` in docker-compose.prod.yml.
 *
 * - In development: .env is also loaded.
 *
 * - If .env is missing: app still works (Docker ENV wins).
 */
$envFile = dirname(__DIR__) . '/.env';

if (is_readable($envFile)) {
    loadEnv($envFile);
}

// --------------------------------------------------------
// Bootstrap Container + Router + Services
// --------------------------------------------------------

$bootstrap = new Bootstrap(dirname(__DIR__));
$dispatcher = $bootstrap->init();

// --------------------------------------------------------
// Build PSR-7 Request (from globals)
// --------------------------------------------------------

$factory = new Psr17Factory();
$creator = new ServerRequestCreator(
    $factory,
    $factory,
    $factory,
    $factory
);

$request = $creator->fromGlobals();

// --------------------------------------------------------
// Dispatch through middleware, router, controllers
// --------------------------------------------------------

$response = $dispatcher->dispatch($request);

// --------------------------------------------------------
// Emit the response
// --------------------------------------------------------

http_response_code($response->getStatusCode());

foreach ($response->getHeaders() as $header => $values) {
    foreach ($values as $value) {
        header("$header: $value", false);
    }
}

echo $response->getBody();
