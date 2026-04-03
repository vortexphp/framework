<?php

declare(strict_types=1);

namespace Vortex\Cache;

use Vortex\AppContext;
use Vortex\Contracts\Cache as CacheContract;

/**
 * Static access to the singleton {@see CacheContract} (same instance as constructor injection).
 */
final class Cache
{
    private static function store(): CacheContract
    {
        return AppContext::container()->make(CacheContract::class);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::store()->get($key, $default);
    }

    public static function set(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        self::store()->set($key, $value, $ttlSeconds);
    }

    public static function forget(string $key): void
    {
        self::store()->forget($key);
    }

    public static function clear(): void
    {
        self::store()->clear();
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function remember(string $key, ?int $ttlSeconds, callable $callback): mixed
    {
        return self::store()->remember($key, $ttlSeconds, $callback);
    }
}
