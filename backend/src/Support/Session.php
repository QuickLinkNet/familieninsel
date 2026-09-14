<?php

declare(strict_types=1);

namespace App\Support;

final class Session
{
    private const KEY_FAMILY_ID = 'family_id';
    private const KEY_PLAYER_ID = 'player_id';
    private const KEY_PLAYER_ROLE = 'player_role';
    private const KEY_CSRF_TOKEN = 'csrf_token';
    private const KEY_LAST_ACTIVITY = 'last_activity';
    private const KEY_LOGIN_ATTEMPTS = 'login_attempts';
    private const KEY_LOGIN_LOCKED_UNTIL = 'login_locked_until';

    /**
     * @param array{cookie_name: string, idle_timeout_seconds: int} $config
     */
    public static function start(array $config): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = (($_SERVER['HTTPS'] ?? '') !== '') || (($_SERVER['SERVER_PORT'] ?? '') === '443');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'secure' => $isHttps,
            'samesite' => 'Lax',
        ]);
        session_name($config['cookie_name']);
        session_start();

        self::enforceIdleTimeout((int) $config['idle_timeout_seconds']);
    }

    private static function enforceIdleTimeout(int $idleTimeoutSeconds): void
    {
        $lastActivity = $_SESSION[self::KEY_LAST_ACTIVITY] ?? null;
        if ($lastActivity !== null && (time() - (int) $lastActivity) > $idleTimeoutSeconds) {
            self::destroy();
        }
        $_SESSION[self::KEY_LAST_ACTIVITY] = time();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /**
     * Verlaengert das Session-Cookie ueber die Browser-Laufzeit hinaus. Wird
     * beim QR-Login von Kindern genutzt, damit das Tablet dauerhaft
     * angemeldet bleibt statt sich beim Schliessen des Browsers abzumelden
     * (normales PHP-Session-Cookie hat lifetime=0, also nur bis Browser zu).
     */
    public static function extendCookieLifetime(int $seconds): void
    {
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires' => time() + $seconds,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function setFamily(int $familyId): void
    {
        $_SESSION[self::KEY_FAMILY_ID] = $familyId;
    }

    public static function familyId(): ?int
    {
        return isset($_SESSION[self::KEY_FAMILY_ID]) ? (int) $_SESSION[self::KEY_FAMILY_ID] : null;
    }

    public static function selectProfile(int $playerId, string $role): void
    {
        $_SESSION[self::KEY_PLAYER_ID] = $playerId;
        $_SESSION[self::KEY_PLAYER_ROLE] = $role;
    }

    public static function playerId(): ?int
    {
        return isset($_SESSION[self::KEY_PLAYER_ID]) ? (int) $_SESSION[self::KEY_PLAYER_ID] : null;
    }

    public static function playerRole(): ?string
    {
        return $_SESSION[self::KEY_PLAYER_ROLE] ?? null;
    }

    public static function csrfToken(): string
    {
        if (!isset($_SESSION[self::KEY_CSRF_TOKEN])) {
            $_SESSION[self::KEY_CSRF_TOKEN] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::KEY_CSRF_TOKEN];
    }

    public static function verifyCsrfToken(?string $token): bool
    {
        $expected = $_SESSION[self::KEY_CSRF_TOKEN] ?? null;

        return $expected !== null && $token !== null && hash_equals($expected, $token);
    }

    public static function registerFailedLoginAttempt(int $maxAttempts, int $lockoutSeconds): void
    {
        $attempts = ((int) ($_SESSION[self::KEY_LOGIN_ATTEMPTS] ?? 0)) + 1;
        $_SESSION[self::KEY_LOGIN_ATTEMPTS] = $attempts;

        if ($attempts >= $maxAttempts) {
            $_SESSION[self::KEY_LOGIN_LOCKED_UNTIL] = time() + $lockoutSeconds;
        }
    }

    public static function resetLoginAttempts(): void
    {
        unset($_SESSION[self::KEY_LOGIN_ATTEMPTS], $_SESSION[self::KEY_LOGIN_LOCKED_UNTIL]);
    }

    public static function isLoginLocked(): bool
    {
        $until = $_SESSION[self::KEY_LOGIN_LOCKED_UNTIL] ?? null;

        return $until !== null && time() < (int) $until;
    }
}
