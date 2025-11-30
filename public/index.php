<?php

use Framework\Bootstrap;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../Framework/helpers.php';

/**
 * --------------------------------------------------------
 * Load Environment (.env optional)
 * --------------------------------------------------------
 */
$envFile = dirname(__DIR__) . '/.env';

if (is_readable($envFile)) {
    loadEnv($envFile);
}

/**
 * --------------------------------------------------------
 * Bootstrap: Container, Router, Services
 * --------------------------------------------------------
 */
$bootstrap   = new Bootstrap(dirname(__DIR__));
$dispatcher  = $bootstrap->init();
$appContainer = $bootstrap->getContainer();   // <-- makes helpers like app(), view() work

/**
 * --------------------------------------------------------
 * Build PSR-7 ServerRequest (from PHP globals)
 * --------------------------------------------------------
 */
$factory = new Psr17Factory();

$creator = new ServerRequestCreator(
    $factory, // server request
    $factory, // uri
    $factory, // uploaded file
    $factory  // stream
);

$request = $creator->fromGlobals();

/**
 * --------------------------------------------------------
 * Dispatch Request → Middleware → Router → Controller
 * --------------------------------------------------------
 */
$response = $dispatcher->dispatch($request);

/**
 * --------------------------------------------------------
 * Emit Response (headers + body)
 * --------------------------------------------------------
 */
http_response_code($response->getStatusCode());

foreach ($response->getHeaders() as $header => $values) {
    foreach ($values as $value) {
        header("$header: $value", false);
    }
}

echo $response->getBody();
