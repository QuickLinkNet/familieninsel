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

    public function index(): void
    {
        if (!RequireAuth::check()) {
            return;
        }

        $buildings = $this->buildingService->getBuildingsForFamily((int) Session::familyId());

        JsonResponse::success(['buildings' => $buildings]);
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
        $body = Request::jsonBody();
        $amounts = is_array($body['amounts'] ?? null) ? $body['amounts'] : [];

        $result = $this->buildingService->contribute($familyId, (int) Session::playerId(), (int) $params['id'], $amounts);

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
