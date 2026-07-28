<?php

declare(strict_types=1);

namespace App\Middleware;

final class Cors
{
    /**
     * @param string[] $allowedOrigins
     */
    public static function handle(array $allowedOrigins): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;

        if ($origin !== null && in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
