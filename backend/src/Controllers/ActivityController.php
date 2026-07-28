<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\RequireAuth;
use App\Repositories\ActivityLogRepository;
use App\Support\JsonResponse;
use App\Support\Session;

final class ActivityController
{
    public function __construct(private readonly ActivityLogRepository $activityLog)
    {
    }

    public function index(): void
    {
        if (!RequireAuth::check()) {
            return;
        }

        $entries = array_map(
            static fn (array $entry): array => [
                'id' => (int) $entry['id'],
                'eventType' => $entry['event_type'],
                'message' => $entry['message'],
                'createdAt' => $entry['created_at'],
            ],
            $this->activityLog->findRecentForFamily((int) Session::familyId(), 20),
        );

        JsonResponse::success(['entries' => $entries]);
    }
}
