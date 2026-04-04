<?php

declare(strict_types=1);

namespace Vortex\Auth;

use InvalidArgumentException;
use Vortex\Http\Session;

/**
 * Session-backed authentication using {@code auth_user_id} (same contract as {@see SessionStore::authUserId()}).
 */
final class Auth
{
    public const SESSION_USER_ID_KEY = 'auth_user_id';

    /** @var null|callable(int): mixed */
    private static $userResolver = null;

    /**
     * @param callable(int): mixed $callback returns the authenticated model/object for an id, or null
     */
    public static function resolveUserUsing(callable $callback): void
    {
        self::$userResolver = $callback;
    }

    public static function login(Authenticatable $user, bool $remember = false): void
    {
        self::loginUsingId($user->authIdentifier(), $remember);
    }

    public static function loginUsingId(int $id, bool $remember = false): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException('User id must be a positive integer.');
        }

        Session::start();
        Session::put(self::SESSION_USER_ID_KEY, $id);
        Session::regenerate();
        if ($remember) {
            RememberCookie::queue($id);
        }
    }

    public static function logout(): void
    {
        RememberCookie::forget();
        Session::flushAuth();
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function guest(): bool
    {
        return ! self::check();
    }

    public static function id(): ?int
    {
        return Session::authUserId();
    }

    public static function user(): mixed
    {
        $id = self::id();
        if ($id === null || self::$userResolver === null) {
            return null;
        }

        return (self::$userResolver)($id);
    }

    /**
     * @internal
     */
    public static function resetForTesting(): void
    {
        self::$userResolver = null;
    }
}
