<?php

declare(strict_types=1);

namespace Tests;

use App\Middleware\Csrf;
use App\Middleware\RequireAuth;
use App\Middleware\RequireParent;
use App\Support\Session;
use PHPUnit\Framework\TestCase;

final class SessionMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            Session::start(['cookie_name' => 'test_session', 'idle_timeout_seconds' => 7200]);
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testRequireAuthFailsWithoutSelectedProfile(): void
    {
        ob_start();
        $result = RequireAuth::check();
        $output = ob_get_clean();

        self::assertFalse($result);
        self::assertSame('UNAUTHENTICATED', json_decode((string) $output, true)['error']['code']);
    }

    public function testRequireAuthSucceedsAfterProfileSelection(): void
    {
        Session::setFamily(1);
        Session::selectProfile(2, 'child');

        self::assertTrue(RequireAuth::check());
    }

    public function testRequireParentFailsForChildRole(): void
    {
        Session::setFamily(1);
        Session::selectProfile(2, 'child');

        ob_start();
        $result = RequireParent::check();
        $output = ob_get_clean();

        self::assertFalse($result);
        self::assertSame('FORBIDDEN', json_decode((string) $output, true)['error']['code']);
    }

    public function testRequireParentSucceedsForParentRole(): void
    {
        Session::setFamily(1);
        Session::selectProfile(3, 'parent');

        self::assertTrue(RequireParent::check());
    }

    public function testLoginLockoutAfterMaxAttempts(): void
    {
        Session::registerFailedLoginAttempt(3, 60);
        Session::registerFailedLoginAttempt(3, 60);
        self::assertFalse(Session::isLoginLocked());

        Session::registerFailedLoginAttempt(3, 60);
        self::assertTrue(Session::isLoginLocked());
    }

    public function testCsrfAllowsSafeMethodsWithoutToken(): void
    {
        self::assertTrue(Csrf::check('GET'));
    }

    public function testCsrfRejectsPostWithoutValidToken(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'falsches-token';
        Session::csrfToken();

        ob_start();
        $result = Csrf::check('POST');
        ob_get_clean();

        self::assertFalse($result);
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    public function testCsrfAcceptsPostWithValidToken(): void
    {
        $token = Session::csrfToken();
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;

        self::assertTrue(Csrf::check('POST'));
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    }
}
