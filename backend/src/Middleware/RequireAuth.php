<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\JsonResponse;
use App\Support\Session;

final class RequireAuth
{
    public static function check(): bool
    {
        if (Session::familyId() === null || Session::playerId() === null) {
            JsonResponse::error(401, 'UNAUTHENTICATED', 'Bitte zuerst anmelden und ein Profil waehlen.');

            return false;
        }

        return true;
    }
}
