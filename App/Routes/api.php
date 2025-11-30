<?php

use Framework\Router\RouteCollector;
use App\Http\Controllers\UserController;

return static function (RouteCollector $routes): void {

    // API prefix group: /api
    $routes->group('/api', function (RouteCollector $routes) {

        // Users resource
        $routes->group('/users', function (RouteCollector $routes) {
            $routes->get('/',  [UserController::class, 'apiIndex']); // GET /api/users
            $routes->get('/{id}', [UserController::class, 'apiShow']); // GET /api/users/{id}
        });

    });

};
