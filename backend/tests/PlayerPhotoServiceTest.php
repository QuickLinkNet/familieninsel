<?php

declare(strict_types=1);

namespace Tests;

use App\Services\PlayerPhotoService;
use PHPUnit\Framework\TestCase;

final class PlayerPhotoServiceTest extends TestCase
{
    private string $storageDir;
    private PlayerPhotoService $service;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir() . '/familieninsel-photos-' . uniqid();
        $this->service = new PlayerPhotoService($this->storageDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->storageDir)) {
            $this->removeDirectory($this->storageDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $item) {
            is_dir($item) ? $this->removeDirectory($item) : unlink($item);
        }
        rmdir($dir);
    }

    private function createTestImage(int $width = 800, int $height = 600): string
    {
        $path = sys_get_temp_dir() . '/familieninsel-test-image-' . uniqid() . '.jpg';
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 100, 150, 200);
        imagefill($image, 0, 0, $color);
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        return $path;
    }

    public function testStoreValidImageSucceeds(): void
    {
        $imagePath = $this->createTestImage();

        $result = $this->service->store(1, 3, $imagePath, (int) filesize($imagePath));

        self::assertTrue($result['success']);
        self::assertTrue($this->service->hasPhoto(1, 3));

        unlink($imagePath);
    }

    public function testStoredImageIsCroppedToSquareAndResized(): void
    {
        $imagePath = $this->createTestImage(800, 600);
        $this->service->store(1, 3, $imagePath, (int) filesize($imagePath));

        $storedPath = $this->service->photoPathFor(1, 3);
        $info = getimagesize($storedPath);

        self::assertSame(512, $info[0]);
        self::assertSame(512, $info[1]);

        unlink($imagePath);
    }

    public function testStoreRejectsNonImageFile(): void
    {
        $path = sys_get_temp_dir() . '/familieninsel-not-an-image-' . uniqid() . '.txt';
        file_put_contents($path, 'Das ist definitiv kein Bild.');

        $result = $this->service->store(1, 3, $path, (int) filesize($path));

        self::assertFalse($result['success']);
        self::assertSame('INVALID_IMAGE', $result['code']);
        self::assertFalse($this->service->hasPhoto(1, 3));

        unlink($path);
    }

    public function testStoreRejectsOversizedFile(): void
    {
        $imagePath = $this->createTestImage(10, 10);

        $result = $this->service->store(1, 3, $imagePath, 6 * 1024 * 1024);

        self::assertFalse($result['success']);
        self::assertSame('FILE_TOO_LARGE', $result['code']);

        unlink($imagePath);
    }

    public function testHasPhotoReturnsFalseWhenNoneUploaded(): void
    {
        self::assertFalse($this->service->hasPhoto(99, 99));
    }

    public function testDifferentPlayersHaveIndependentPhotos(): void
    {
        $imagePath = $this->createTestImage();
        $this->service->store(1, 3, $imagePath, (int) filesize($imagePath));

        self::assertTrue($this->service->hasPhoto(1, 3));
        self::assertFalse($this->service->hasPhoto(1, 4));
        self::assertFalse($this->service->hasPhoto(2, 3));

        unlink($imagePath);
    }
}
