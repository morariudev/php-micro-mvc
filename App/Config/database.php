<?php

$default = realpath(__DIR__ . '/../../database/database.sqlite');

$path = env('DB_PATH');
if (empty($path)) {
    $path = $default;
}

return [
    'driver'   => 'sqlite',
    'database' => $path,
];
