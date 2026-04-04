<?php

declare(strict_types=1);

namespace Vortex\Cache;

use DateInterval;
use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;
use Traversable;
use Vortex\Contracts\Cache as CacheContract;

/**
 * Bridges {@see CacheContract} to PSR-16 {@see CacheInterface} (default {@see CacheContract} from the container).
 */
final class Psr16Cache implements CacheInterface
{
    public function __construct(
        private readonly CacheContract $inner,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->assertKey($key);

        return $this->inner->get($key, $default);
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->assertKey($key);
        try {
            $this->inner->set($key, $value, $this->normalizeTtl($ttl));
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        $this->assertKey($key);
        try {
            $this->inner->forget($key);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    public function clear(): bool
    {
        try {
            $this->inner->clear();
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $this->assertIterableKeys($keys);
        $out = [];
        foreach ($keys as $key) {
            $this->assertKey((string) $key);
            $k = (string) $key;
            $out[$k] = $this->inner->get($k, $default);
        }

        return $out;
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        if (! is_array($values) && ! $values instanceof Traversable) {
            throw new SimpleCacheInvalidArgumentException('Values must be an array or Traversable.');
        }
        $ttlSeconds = $this->normalizeTtl($ttl);
        try {
            foreach ($values as $key => $value) {
                if (! is_string($key) && ! is_int($key)) {
                    throw new SimpleCacheInvalidArgumentException('Cache key must be string or int.');
                }
                $this->assertKey((string) $key);
                $this->inner->set((string) $key, $value, $ttlSeconds);
            }
        } catch (SimpleCacheInvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $this->assertIterableKeys($keys);
        try {
            foreach ($keys as $key) {
                $this->assertKey((string) $key);
                $this->inner->forget((string) $key);
            }
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    public function has(string $key): bool
    {
        $this->assertKey($key);
        $sentinel = new \stdClass();
        $value = $this->inner->get($key, $sentinel);

        return $value !== $sentinel;
    }

    private function assertKey(string $key): void
    {
        if ($key === '') {
            throw new SimpleCacheInvalidArgumentException('Cache key must not be empty.');
        }
    }

    /**
     * @phpstan-assert array<mixed>|Traversable $keys
     */
    private function assertIterableKeys(iterable $keys): void
    {
        if (! is_array($keys) && ! $keys instanceof Traversable) {
            throw new SimpleCacheInvalidArgumentException('Keys must be an array or Traversable.');
        }
    }

    private function normalizeTtl(null|int|DateInterval $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }
        if (is_int($ttl)) {
            return max(0, $ttl);
        }

        $start = new DateTimeImmutable();
        $end = $start->add($ttl);

        return max(0, $end->getTimestamp() - $start->getTimestamp());
    }
}
