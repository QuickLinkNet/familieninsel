<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\RequireAuth;
use App\Repositories\PlayerRepository;
use App\Services\PlayerPhotoService;
use App\Support\JsonResponse;
use App\Support\Session;

final class PlayerPhotoController
{
    public function __construct(
        private readonly PlayerPhotoService $photoService,
        private readonly PlayerRepository $players,
    ) {
    }

    /**
     * @param array{id: string} $params
     */
    public function upload(array $params): void
    {
        if (!RequireAuth::check()) {
            return;
        }

        $familyId = (int) Session::familyId();
        $targetPlayerId = (int) $params['id'];

        if (!$this->canManagePhoto($familyId, $targetPlayerId)) {
            JsonResponse::error(403, 'FORBIDDEN', 'Du darfst dieses Profilfoto nicht aendern.');

            return;
        }

        $uploadedFile = $_FILES['photo'] ?? null;
        if ($uploadedFile === null || ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            JsonResponse::error(422, 'VALIDATION_ERROR', 'Bitte ein Foto auswaehlen.');

            return;
        }

        if (!is_uploaded_file($uploadedFile['tmp_name'])) {
            JsonResponse::error(422, 'VALIDATION_ERROR', 'Ungueltiger Upload.');

            return;
        }

        $result = $this->photoService->store(
            $familyId,
            $targetPlayerId,
            $uploadedFile['tmp_name'],
            (int) $uploadedFile['size'],
        );

        if (!$result['success']) {
            $statusCode = match ($result['code']) {
                'FILE_TOO_LARGE', 'INVALID_IMAGE' => 422,
                default => 500,
            };
            JsonResponse::error($statusCode, $result['code'], $result['message']);

            return;
        }

        JsonResponse::success([]);
    }

    /**
     * @param array{id: string} $params
     */
    public function show(array $params): void
    {
        if (!RequireAuth::check()) {
            return;
        }

        $familyId = (int) Session::familyId();
        $targetPlayerId = (int) $params['id'];

        if ($this->players->findActiveByIdAndFamily($targetPlayerId, $familyId) === null) {
            http_response_code(404);

            return;
        }

        $path = $this->photoService->photoPathFor($familyId, $targetPlayerId);
        if (!is_file($path)) {
            http_response_code(404);

            return;
        }

        header('Content-Type: image/jpeg');
        header('Cache-Control: private, max-age=3600');
        readfile($path);
    }

    private function canManagePhoto(int $familyId, int $targetPlayerId): bool
    {
        if (Session::playerId() === $targetPlayerId) {
            return true;
        }

        if (Session::playerRole() === 'parent') {
            return $this->players->findActiveByIdAndFamily($targetPlayerId, $familyId) !== null;
        }

        return false;
    }
}
