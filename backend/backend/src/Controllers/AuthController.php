<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AuthController
{
    public function login(Request $request): void
    {
        $data = $request->body();
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || $password === '') {
            jsonResponse(['ok' => false, 'message' => 'Usuário e senha são obrigatórios.'], 422);
            return;
        }

        $auth = AuthService::attemptAdmin($username, $password);
        if (!$auth) {
            jsonResponse(['ok' => false, 'message' => 'Credenciais inválidas.'], 401);
            return;
        }

        jsonResponse(['ok' => true, 'data' => $auth]);
    }

    public function me(Request $request): void
    {
        $user = AuthService::guard($request);
        if (!$user) {
            jsonResponse(['ok' => false, 'message' => 'Não autorizado.'], 401);
            return;
        }

        jsonResponse(['ok' => true, 'data' => $user]);
    }
}
