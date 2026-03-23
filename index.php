<?php

declare(strict_types=1);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

require __DIR__ . '/backend/src/Support/helpers.php';

$allowedOrigins = [];
$frontendUrls = (string) env('APP_FRONTEND_URLS', '');
if ($frontendUrls !== '') {
    foreach (explode(',', $frontendUrls) as $url) {
        $url = rtrim(trim($url), '/');
        if ($url !== '') {
            $allowedOrigins[] = $url;
        }
    }
}

$singleFrontend = rtrim((string) config('app.frontend_url', ''), '/');
if ($singleFrontend !== '') {
    $allowedOrigins[] = $singleFrontend;
}

$allowedOrigins = array_values(array_unique(array_filter($allowedOrigins)));

if ($origin !== '' && in_array(rtrim($origin, '/'), $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . rtrim($origin, '/'));
    header('Access-Control-Allow-Credentials: true');
}

header('Vary: Origin');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/backend/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

use App\Controllers\AuthController;
use App\Controllers\CouponController;
use App\Controllers\CustomerController;
use App\Controllers\OrderController;
use App\Controllers\ProductController;
use App\Controllers\ShippingController;
use App\Controllers\UploadController;
use App\Core\Request;
use App\Core\Router;

$router = new Router();
$request = new Request();

$router->add('GET', '/', function () {
    jsonResponse([
        'ok' => true,
        'message' => 'API Autentica Fashion online.',
    ]);
});

$router->add('POST', '/api/auth/login', [AuthController::class, 'login']);
$router->add('GET', '/api/auth/me', [AuthController::class, 'me'], true);

$router->add('POST', '/api/customers/register', [CustomerController::class, 'register']);
$router->add('POST', '/api/customers/login', [CustomerController::class, 'login']);
$router->add('GET', '/api/customers', [CustomerController::class, 'index'], true);
$router->add('GET', '/api/customers/me', [CustomerController::class, 'me'], true);
$router->add('GET', '/api/customers/orders', [CustomerController::class, 'orders'], true);

$router->add('GET', '/api/products', [ProductController::class, 'index']);
$router->add('GET', '/api/products/{id}', [ProductController::class, 'show']);
$router->add('POST', '/api/products', [ProductController::class, 'store'], true);
$router->add('PUT', '/api/products/{id}', [ProductController::class, 'update'], true);
$router->add('DELETE', '/api/products/{id}', [ProductController::class, 'destroy'], true);

$router->add('GET', '/api/coupons', [CouponController::class, 'index'], true);
$router->add('POST', '/api/coupons', [CouponController::class, 'store'], true);
$router->add('PUT', '/api/coupons/{id}', [CouponController::class, 'update'], true);
$router->add('DELETE', '/api/coupons/{id}', [CouponController::class, 'destroy'], true);
$router->add('GET', '/api/coupons/validate', [CouponController::class, 'validateCoupon']);

$router->add('GET', '/api/orders', [OrderController::class, 'index'], true);
$router->add('POST', '/api/orders', [OrderController::class, 'store']);
$router->add('PATCH', '/api/orders/{id}/status', [OrderController::class, 'updateStatus'], true);
$router->add('PATCH', '/api/orders/{id}/tracking', [OrderController::class, 'updateTracking'], true);

$router->add('POST', '/api/upload/image', [UploadController::class, 'image'], true);
$router->add('GET', '/api/shipping/estimate', [ShippingController::class, 'estimate']);

$router->dispatch($request);
