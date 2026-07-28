<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\JsonResponse;
use App\Support\Session;

final class RequireFamilySession
{
    public static function check(): bool
    {
        if (Session::familyId() === null) {
            JsonResponse::error(401, 'UNAUTHENTICATED', 'Bitte zuerst mit dem Familiencode anmelden.');

            return false;
        }

        return true;
    }
}
