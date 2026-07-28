<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\RequireAuth;
use App\Repositories\ResourceRepository;
use App\Support\JsonResponse;
use App\Support\Session;

final class ResourcesController
{
    public function __construct(private readonly ResourceRepository $resources)
    {
    }

    public function index(): void
    {
        if (!RequireAuth::check()) {
            return;
        }

        $balances = $this->resources->findFamilyBalances((int) Session::familyId());

        $resources = array_map(
            static fn (array $resource): array => [
                'id' => (int) $resource['id'],
                'key' => $resource['key'],
                'name' => $resource['name'],
                'iconKey' => $resource['icon_key'],
                'amount' => $balances[(int) $resource['id']] ?? 0,
            ],
            $this->resources->findAllActive(),
        );

        JsonResponse::success(['resources' => $resources]);
    }
}
