<?php

declare(strict_types=1);

namespace App\Support;

final class JsonResponse
{
    /**
     * @param array<string, mixed> $data
     */
    public static function success(array $data, string $message = ''): void
    {
        $payload = [
            'success' => true,
            'data' => $data,
        ];

        if ($message !== '') {
            $payload['message'] = $message;
        }

        self::send(200, $payload);
    }

    public static function error(int $statusCode, string $code, string $message): void
    {
        self::send($statusCode, [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function send(int $statusCode, array $payload): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
