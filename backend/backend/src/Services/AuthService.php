<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;

class AuthService
{
    public static function attemptAdmin(string $username, string $password): ?array
    {
        $expectedUser = config('auth.admin_username');
        $expectedPass = config('auth.admin_password');

        if ($username !== $expectedUser || $password !== $expectedPass) {
            return null;
        }

        $payload = [
            'sub' => 'admin',
            'username' => $username,
            'role' => 'admin',
            'iat' => time(),
            'exp' => time() + (int) config('auth.jwt_expires_in'),
        ];

        return [
            'token' => JwtService::encode($payload),
            'user' => [
                'username' => $username,
                'role' => 'admin',
            ],
        ];
    }

    public static function guard(Request $request): ?array
    {
        $token = $request->bearerToken();
        if (!$token) {
            return null;
        }
        return JwtService::decode($token);
    }

    public static function issueCustomerToken(array $customer): array
    {
        $payload = [
            'sub' => $customer['id'],
            'email' => $customer['email'],
            'name' => $customer['name'],
            'role' => 'customer',
            'iat' => time(),
            'exp' => time() + (int) config('auth.jwt_expires_in'),
        ];

        return [
            'token' => JwtService::encode($payload),
            'user' => $customer,
        ];
    }
}
