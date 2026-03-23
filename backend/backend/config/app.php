<?php

declare(strict_types=1);

return [
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
    'url' => env('APP_URL', ''),
    'frontend_url' => env('APP_FRONTEND_URL', '*'),
    'key' => env('APP_KEY', ''),
];
