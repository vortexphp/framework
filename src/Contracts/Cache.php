<?php

declare(strict_types=1);

namespace Vortex\Contracts;

interface Cache
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void;

    public function forget(string $key): void;

    /**
     * Remove every entry this backend instance owns (for this store path / prefix).
     */
    public function clear(): void;

    /**
     * Return cached value or invoke the callback, store its result, and return it.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function remember(string $key, ?int $ttlSeconds, callable $callback): mixed;
}
