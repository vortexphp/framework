<?php

declare(strict_types=1);

namespace Vortex\Auth;

use RuntimeException;
use Vortex\Config\Repository;

/**
 * Reads {@code auth.*} config when {@see Repository} is initialized; falls back when not (e.g. early tests).
 */
final class AuthConfig
{
    public static function loginPath(): string
    {
        $v = self::get('auth.login_path', '/login');

        return is_string($v) && $v !== '' ? $v : '/login';
    }

    public static function rememberCookieName(): string
    {
        $v = self::get('auth.remember_cookie', 'remember_web');

        return is_string($v) && $v !== '' ? $v : 'remember_web';
    }

    public static function rememberSeconds(): int
    {
        $v = self::get('auth.remember_seconds', 60 * 60 * 24 * 14);

        return max(60, (int) $v);
    }

    public static function cookieSecure(): bool
    {
        return (bool) self::get('auth.cookie_secure', false);
    }

    public static function cookieSameSite(): string
    {
        $v = self::get('auth.cookie_samesite', 'Lax');

        return is_string($v) && $v !== '' ? $v : 'Lax';
    }

    private static function get(string $key, mixed $default): mixed
    {
        try {
            return Repository::get($key, $default);
        } catch (RuntimeException) {
            return $default;
        }
    }
}
