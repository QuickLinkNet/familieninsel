<?php

declare(strict_types=1);

namespace App\Services;

use GdImage;

final class PlayerPhotoService
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;
    private const TARGET_SIZE = 512;

    public function __construct(private readonly string $storageDirectory)
    {
    }

    public function photoPathFor(int $familyId, int $playerId): string
    {
        return "{$this->storageDirectory}/{$familyId}/{$playerId}.jpg";
    }

    public function hasPhoto(int $familyId, int $playerId): bool
    {
        return is_file($this->photoPathFor($familyId, $playerId));
    }

    /**
     * Nimmt einen bereits validierten (is_uploaded_file-geprueften) Dateipfad
     * entgegen - diese Pruefung liegt bewusst im Controller, damit dieser
     * Service ohne echten HTTP-Upload testbar bleibt.
     *
     * @return array{success: true}|array{success: false, code: string, message: string}
     */
    public function store(int $familyId, int $playerId, string $sourcePath, int $fileSize): array
    {
        if ($fileSize <= 0 || $fileSize > self::MAX_FILE_SIZE) {
            return $this->error('FILE_TOO_LARGE', 'Das Bild darf hoechstens 5 MB gross sein.');
        }

        $info = @getimagesize($sourcePath);
        if ($info === false) {
            return $this->error('INVALID_IMAGE', 'Bitte ein JPEG-, PNG- oder WebP-Bild hochladen.');
        }

        $sourceImage = $this->loadImage($sourcePath, $info[2]);
        if ($sourceImage === null) {
            return $this->error('INVALID_IMAGE', 'Das Bild wird nicht unterstuetzt (JPEG, PNG oder WebP erwartet).');
        }

        $cropped = $this->cropToSquare($sourceImage);
        imagedestroy($sourceImage);

        $resized = imagescale($cropped, self::TARGET_SIZE, self::TARGET_SIZE);
        imagedestroy($cropped);

        if ($resized === false) {
            return $this->error('PROCESSING_FAILED', 'Das Bild konnte nicht verarbeitet werden.');
        }

        $targetPath = $this->photoPathFor($familyId, $playerId);
        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $saved = imagejpeg($resized, $targetPath, 85);
        imagedestroy($resized);

        if (!$saved) {
            return $this->error('PROCESSING_FAILED', 'Das Bild konnte nicht gespeichert werden.');
        }

        return ['success' => true];
    }

    private function loadImage(string $path, int $imageType): ?GdImage
    {
        $image = match ($imageType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        return $image instanceof GdImage ? $image : null;
    }

    private function cropToSquare(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);
        $x = (int) (($width - $side) / 2);
        $y = (int) (($height - $side) / 2);

        $cropped = imagecreatetruecolor($side, $side);
        imagecopy($cropped, $image, 0, 0, $x, $y, $side, $side);

        return $cropped;
    }

    /**
     * @return array{success: false, code: string, message: string}
     */
    private function error(string $code, string $message): array
    {
        return ['success' => false, 'code' => $code, 'message' => $message];
    }
}
