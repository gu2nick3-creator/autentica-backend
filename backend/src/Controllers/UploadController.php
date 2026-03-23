<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\CloudinaryService;

class UploadController
{
    public function image(Request $request): void
    {
        $data = $request->body();
        if (empty($data['file'])) {
            jsonResponse(['ok' => false, 'message' => 'Arquivo base64 é obrigatório.'], 422);
            return;
        }

        try {
            $result = CloudinaryService::uploadBase64((string) $data['file'], (string) ($data['filename'] ?? 'produto'));
            jsonResponse(['ok' => true, 'data' => $result], 201);
        } catch (\Throwable $e) {
            jsonResponse(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
