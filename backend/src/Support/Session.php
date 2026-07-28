<?php

declare(strict_types=1);

namespace App\Support;

final class Session
{
    private const KEY_FAMILY_ID = 'family_id';
    private const KEY_PLAYER_ID = 'player_id';
    private const KEY_PLAYER_ROLE = 'player_role';
    private const KEY_PARENT_UNLOCKED_UNTIL = 'parent_unlocked_until';
    private const KEY_CSRF_TOKEN = 'csrf_token';
    private const KEY_LAST_ACTIVITY = 'last_activity';
    private const KEY_PIN_ATTEMPTS = 'pin_attempts';
    private const KEY_PIN_LOCKED_UNTIL = 'pin_locked_until';

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
        unset($_SESSION[self::KEY_PARENT_UNLOCKED_UNTIL]);
    }

    public static function playerId(): ?int
    {
        return isset($_SESSION[self::KEY_PLAYER_ID]) ? (int) $_SESSION[self::KEY_PLAYER_ID] : null;
    }

    public static function playerRole(): ?string
    {
        return $_SESSION[self::KEY_PLAYER_ROLE] ?? null;
    }

    public static function unlockParent(int $ttlSeconds): void
    {
        $_SESSION[self::KEY_PARENT_UNLOCKED_UNTIL] = time() + $ttlSeconds;
    }

    public static function isParentUnlocked(): bool
    {
        $until = $_SESSION[self::KEY_PARENT_UNLOCKED_UNTIL] ?? null;
        return $until !== null && time() < (int) $until;
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

    public static function registerFailedPinAttempt(int $maxAttempts, int $lockoutSeconds): void
    {
        $attempts = ((int) ($_SESSION[self::KEY_PIN_ATTEMPTS] ?? 0)) + 1;
        $_SESSION[self::KEY_PIN_ATTEMPTS] = $attempts;

        if ($attempts >= $maxAttempts) {
            $_SESSION[self::KEY_PIN_LOCKED_UNTIL] = time() + $lockoutSeconds;
        }
    }

    public static function resetPinAttempts(): void
    {
        unset($_SESSION[self::KEY_PIN_ATTEMPTS], $_SESSION[self::KEY_PIN_LOCKED_UNTIL]);
    }

    public static function isPinLocked(): bool
    {
        $until = $_SESSION[self::KEY_PIN_LOCKED_UNTIL] ?? null;

        return $until !== null && time() < (int) $until;
    }
}
