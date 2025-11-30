<?php

use Framework\Router\RouteCollector;
use App\Http\Controllers\UserController;

return static function (RouteCollector $routes): void {
    $routes->get('/api/users', [UserController::class, 'apiIndex']);
    $routes->get('/api/user/{id}', [UserController::class, 'apiShow']);
};
