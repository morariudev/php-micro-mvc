<?php

use Framework\Router\RouteCollector;
use App\Http\Controllers\UserController;

return static function (RouteCollector $routes): void {

    // Public homepage
    $routes->get('/', [UserController::class, 'index']);

    // User HTML pages
    $routes->group('/user', function (RouteCollector $routes) {
        $routes->get('/{id}', [UserController::class, 'show']);
    });

};
