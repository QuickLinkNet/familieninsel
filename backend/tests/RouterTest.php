<?php

declare(strict_types=1);

namespace Tests;

use App\Support\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testDispatchCallsRegisteredHandler(): void
    {
        $router = new Router();
        $called = false;

        $router->get('/health', function () use (&$called): void {
            $called = true;
        });

        $router->dispatch('GET', '/health');

        self::assertTrue($called);
    }

    public function testDispatchReturnsNotFoundForUnknownRoute(): void
    {
        $router = new Router();

        ob_start();
        $router->dispatch('GET', '/unbekannt');
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);

        self::assertSame(404, http_response_code());
        self::assertSame('NOT_FOUND', $decoded['error']['code']);
    }
}
