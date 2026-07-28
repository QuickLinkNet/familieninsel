<?php

declare(strict_types=1);

namespace Tests;

use App\Support\JsonResponse;
use PHPUnit\Framework\TestCase;

final class JsonResponseTest extends TestCase
{
    public function testSuccessResponseContainsDataAndDefaultsWithoutMessage(): void
    {
        ob_start();
        JsonResponse::success(['status' => 'ok']);
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);

        self::assertSame(200, http_response_code());
        self::assertTrue($decoded['success']);
        self::assertSame(['status' => 'ok'], $decoded['data']);
        self::assertArrayNotHasKey('message', $decoded);
    }

    public function testErrorResponseContainsCodeAndMessage(): void
    {
        ob_start();
        JsonResponse::error(404, 'NOT_FOUND', 'Route wurde nicht gefunden.');
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);

        self::assertSame(404, http_response_code());
        self::assertFalse($decoded['success']);
        self::assertSame('NOT_FOUND', $decoded['error']['code']);
        self::assertSame('Route wurde nicht gefunden.', $decoded['error']['message']);
    }
}
