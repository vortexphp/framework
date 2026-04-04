<?php

declare(strict_types=1);

namespace Vortex\Cache;

use Vortex\AppContext;
use Vortex\Contracts\Cache as CacheContract;

/**
 * Static access to cache stores (same instances as constructor injection of {@see CacheContract} for the default store).
 */
final class Cache
{
    public static function store(?string $name = null): CacheContract
    {
        return AppContext::container()->make(CacheManager::class)->store($name);
    }

    private static function defaultStore(): CacheContract
    {
        return self::store();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::defaultStore()->get($key, $default);
    }

    public static function set(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        self::defaultStore()->set($key, $value, $ttlSeconds);
    }

    public static function add(string $key, mixed $value, int $ttlSeconds): bool
    {
        return self::defaultStore()->add($key, $value, $ttlSeconds);
    }

    public static function forget(string $key): void
    {
        self::defaultStore()->forget($key);
    }

    public static function clear(): void
    {
        self::defaultStore()->clear();
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function remember(string $key, ?int $ttlSeconds, callable $callback): mixed
    {
        return self::defaultStore()->remember($key, $ttlSeconds, $callback);
    }
}
