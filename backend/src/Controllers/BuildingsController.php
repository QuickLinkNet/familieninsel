<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\RequireAuth;
use App\Middleware\RequireParent;
use App\Services\BuildingService;
use App\Support\JsonResponse;
use App\Support\Request;
use App\Support\Session;

final class BuildingsController
{
    public function __construct(private readonly BuildingService $buildingService)
    {
    }

    public function active(): void
    {
        if (!RequireAuth::check()) {
            return;
        }

        $building = $this->buildingService->getActiveBuildingForFamily((int) Session::familyId());
        if ($building === null) {
            JsonResponse::error(404, 'BUILDING_NOT_FOUND', 'Kein aktives Bauprojekt gefunden.');

            return;
        }

        JsonResponse::success(['building' => $building]);
    }

    /**
     * @param array{id: string} $params
     */
    public function contribute(array $params): void
    {
        if (!RequireParent::check()) {
            return;
        }

        $familyId = (int) Session::familyId();
        $active = $this->buildingService->getActiveBuildingForFamily($familyId);
        if ($active === null || $active['id'] !== (int) $params['id']) {
            JsonResponse::error(404, 'BUILDING_NOT_FOUND', 'Kein passendes Bauprojekt gefunden.');

            return;
        }

        $body = Request::jsonBody();
        $amounts = is_array($body['amounts'] ?? null) ? $body['amounts'] : [];

        $result = $this->buildingService->contribute($familyId, (int) Session::playerId(), $amounts);

        if (!$result['success']) {
            $statusCode = match ($result['code']) {
                'BUILDING_NOT_FOUND' => 404,
                'VALIDATION_ERROR', 'INSUFFICIENT_RESOURCES', 'BUILDING_ALREADY_COMPLETED' => 422,
                default => 400,
            };
            JsonResponse::error($statusCode, $result['code'], $result['message']);

            return;
        }

        JsonResponse::success(['justCompleted' => $result['justCompleted']]);
    }
}
