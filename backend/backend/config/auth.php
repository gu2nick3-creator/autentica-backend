<?php

declare(strict_types=1);

return [
    'admin_username' => env('ADMIN_USERNAME', 'adm'),
    'admin_password' => env('ADMIN_PASSWORD', 'admin123@'),
    'jwt_secret' => env('JWT_SECRET', ''),
    'jwt_expires_in' => (int) env('JWT_EXPIRES_IN', '86400'),
];
