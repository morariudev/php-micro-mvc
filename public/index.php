<?php

use Framework\Bootstrap;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../Framework/helpers.php';

// Load environment file (.env, .env.dev, or .env.prod)
$envFile = __DIR__ . '/../.env';

if (file_exists(__DIR__ . '/../.env.dev')) {
    $envFile = __DIR__ . '/../.env.dev';
}

if (file_exists(__DIR__ . '/../.env.prod')) {
    $envFile = __DIR__ . '/../.env.prod';
}

loadEnv($envFile);

// Bootstrap
$bootstrap = new Bootstrap(dirname(__DIR__));
$dispatcher = $bootstrap->init();

$psr17Factory = new Psr17Factory();
$creator = new ServerRequestCreator(
    $psr17Factory,
    $psr17Factory,
    $psr17Factory,
    $psr17Factory
);

$request = $creator->fromGlobals();
$response = $dispatcher->dispatch($request);

// Emit response
http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $key => $values) {
    foreach ($values as $value) {
        header("$key: $value", false);
    }
}

echo $response->getBody();
