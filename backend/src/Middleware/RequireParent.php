<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\JsonResponse;
use App\Support\Session;

final class RequireParent
{
    public static function check(): bool
    {
        if (!RequireAuth::check()) {
            return false;
        }

        if (Session::playerRole() !== 'parent') {
            JsonResponse::error(403, 'FORBIDDEN', 'Diese Aktion ist nur fuer Eltern verfuegbar.');

            return false;
        }

        if (!Session::isParentUnlocked()) {
            JsonResponse::error(403, 'PARENT_PIN_REQUIRED', 'Bitte zuerst die Eltern-PIN eingeben.');

            return false;
        }

        return true;
    }
}
