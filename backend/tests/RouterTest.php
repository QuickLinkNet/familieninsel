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

    public function testDispatchExtractsPathParameters(): void
    {
        $router = new Router();
        $received = null;

        $router->get('/tasks/{id}', function (array $params) use (&$received): void {
            $received = $params;
        });

        $router->dispatch('GET', '/tasks/42');

        self::assertSame(['id' => '42'], $received);
    }

    public function testDispatchSupportsMultipleSegmentsAfterParameter(): void
    {
        $router = new Router();
        $received = null;

        $router->post('/tasks/{id}/complete', function (array $params) use (&$received): void {
            $received = $params;
        });

        $router->dispatch('POST', '/tasks/7/complete');

        self::assertSame(['id' => '7'], $received);
    }

    public function testPutAndDeleteAreRoutable(): void
    {
        $router = new Router();
        $putCalled = false;
        $deleteCalled = false;

        $router->put('/tasks/{id}', function () use (&$putCalled): void {
            $putCalled = true;
        });
        $router->delete('/tasks/{id}', function () use (&$deleteCalled): void {
            $deleteCalled = true;
        });

        $router->dispatch('PUT', '/tasks/1');
        $router->dispatch('DELETE', '/tasks/1');

        self::assertTrue($putCalled);
        self::assertTrue($deleteCalled);
    }
}
