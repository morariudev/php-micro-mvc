<?php

return [
    'name' => 'Mini PHP Framework',
    'env' => env('APP_ENV', 'local'),
    'debug' => filter_var(env('APP_DEBUG', true), FILTER_VALIDATE_BOOL),
    'url' => env('APP_URL', 'http://localhost'),
];
