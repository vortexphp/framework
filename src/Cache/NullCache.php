<?php

declare(strict_types=1);

namespace Vortex\Cache;

use Vortex\Contracts\Cache;

/**
 * No-op store: use when `cache.driver` is `null` or for tests.
 */
final class NullCache implements Cache
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
    }

    public function add(string $key, mixed $value, int $ttlSeconds): bool
    {
        return true;
    }

    public function forget(string $key): void
    {
    }

    public function clear(): void
    {
    }

    public function remember(string $key, ?int $ttlSeconds, callable $callback): mixed
    {
        return $callback();
    }
}
