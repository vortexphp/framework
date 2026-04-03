<?php

declare(strict_types=1);

namespace Vortex\Http;

use RuntimeException;
use Vortex\AppContext;

/**
 * Static facade over the default session store from {@see SessionManager}.
 */
final class Session
{
    private static ?self $instance = null;

    public function __construct(private readonly SessionStore $store)
    {
    }

    public static function setInstance(self $session): void
    {
        self::$instance = $session;
    }

    public static function store(?string $name = null): SessionStore
    {
        return AppContext::container()->make(SessionManager::class)->store($name);
    }

    private static function resolved(): self
    {
        if (self::$instance === null) {
            throw new RuntimeException('Session is not initialized; call Session::setInstance() from bootstrap.');
        }

        return self::$instance;
    }

    public static function start(): void
    {
        self::resolved()->store->start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::resolved()->store->get($key, $default);
    }

    public static function put(string $key, mixed $value): void
    {
        self::resolved()->store->put($key, $value);
    }

    public static function forget(string $key): void
    {
        self::resolved()->store->forget($key);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        return self::resolved()->store->pull($key, $default);
    }

    public static function regenerate(): void
    {
        self::resolved()->store->regenerate();
    }

    /**
     * One-request flash: one argument reads and consumes; two arguments write.
     */
    public static function flash(string $key, mixed $value = null): mixed
    {
        $store = self::resolved()->store;

        return func_num_args() === 1 ? $store->flashGet($key) : $store->flashPut($key, $value);
    }

    public static function flushAuth(): void
    {
        self::resolved()->store->flushAuth();
    }

    public static function authUserId(): ?int
    {
        return self::resolved()->store->authUserId();
    }
}
