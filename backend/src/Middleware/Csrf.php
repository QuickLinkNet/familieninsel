<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\JsonResponse;
use App\Support\Session;

final class Csrf
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public static function check(string $method): bool
    {
        if (in_array($method, self::SAFE_METHODS, true)) {
            return true;
        }

        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!Session::verifyCsrfToken($token)) {
            JsonResponse::error(403, 'CSRF_INVALID', 'Ungueltiges oder fehlendes CSRF-Token.');

            return false;
        }

        return true;
    }
}
