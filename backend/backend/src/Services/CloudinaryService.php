<?php

declare(strict_types=1);

namespace App\Services;

class CloudinaryService
{
    public static function uploadBase64(string $base64, string $filename = 'produto'): array
    {
        $cloudName = config('cloudinary.cloud_name');
        $apiKey = config('cloudinary.api_key');
        $apiSecret = config('cloudinary.api_secret');
        $folder = config('cloudinary.folder');

        if (!$cloudName || !$apiKey || !$apiSecret) {
            throw new \RuntimeException('Cloudinary não configurado.');
        }

        $timestamp = time();
        $publicId = $folder . '/' . pathinfo($filename, PATHINFO_FILENAME) . '-' . $timestamp;
        $signatureBase = "folder={$folder}&public_id={$publicId}&timestamp={$timestamp}{$apiSecret}";
        $signature = sha1($signatureBase);

        $endpoint = "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload";
        $postFields = [
            'file' => $base64,
            'api_key' => $apiKey,
            'timestamp' => (string) $timestamp,
            'signature' => $signature,
            'folder' => $folder,
            'public_id' => $publicId,
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $status >= 400) {
            throw new \RuntimeException($error ?: 'Falha ao enviar imagem para o Cloudinary.');
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || empty($decoded['secure_url'])) {
            throw new \RuntimeException('Resposta inválida do Cloudinary.');
        }

        return [
            'url' => $decoded['secure_url'],
            'public_id' => $decoded['public_id'] ?? null,
        ];
    }
}
