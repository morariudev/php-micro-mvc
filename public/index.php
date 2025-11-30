<?php

use Framework\Bootstrap;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../Framework/helpers.php';

/**
 * Environment Loading:
 * --------------------
 * Docker now provides APP_ENV, APP_DEBUG, APP_URL.
 * We only load .env as an optional fallback.
 */
$envFile = dirname(__DIR__) . '/.env';

if (file_exists($envFile)) {
    loadEnv($envFile);
}

// Bootstrap application
$bootstrap = new Bootstrap(dirname(__DIR__));
$dispatcher = $bootstrap->init();

// Create PSR-7 server request
$psr17Factory = new Psr17Factory();
$creator = new ServerRequestCreator(
    $psr17Factory,
    $psr17Factory,
    $psr17Factory,
    $psr17Factory
);

$request = $creator->fromGlobals();

// Dispatch request through middleware + router
$response = $dispatcher->dispatch($request);

// Emit response headers + status
http_response_code($response->getStatusCode());

foreach ($response->getHeaders() as $key => $values) {
    foreach ($values as $value) {
        header("$key: $value", false);
    }
}

// Emit body
echo $response->getBody();
