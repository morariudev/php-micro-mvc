<?php

use Framework\Router\RouteCollector;
use App\Http\Controllers\UserController;

return static function (RouteCollector $routes): void {
    $routes->get('/', [UserController::class, 'index']);
    $routes->get('/user/{id}', [UserController::class, 'show']);   // ← ADD THIS
};
