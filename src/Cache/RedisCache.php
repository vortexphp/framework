<?php

declare(strict_types=1);

namespace Vortex\Cache;

use Redis;
use RuntimeException;
use Throwable;
use Vortex\Contracts\Cache as CacheContract;

/**
 * Redis-backed cache via phpredis ({@see Redis}). Values are PHP-serialized; use a dedicated Redis logical DB or a unique key prefix.
 */
final class RedisCache implements CacheContract
{
    public function __construct(
        private readonly Redis $redis,
        private readonly string $prefix,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $namespaced = $this->namespacedKey($key);
        $raw = $this->redis->get($namespaced);
        if ($raw === false) {
            return $default;
        }

        try {
            return unserialize((string) $raw, ['allowed_classes' => true]);
        } catch (Throwable) {
            $this->redis->del($namespaced);

            return $default;
        }
    }

    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        $payload = serialize($value);
        $k = $this->namespacedKey($key);
        if ($ttlSeconds === null) {
            if ($this->redis->set($k, $payload) === false) {
                throw new RuntimeException('Redis SET failed for cache key.');
            }

            return;
        }

        $ttl = max(1, $ttlSeconds);
        if ($this->redis->setex($k, $ttl, $payload) === false) {
            throw new RuntimeException('Redis SETEX failed for cache key.');
        }
    }

    public function forget(string $key): void
    {
        $this->redis->del($this->namespacedKey($key));
    }

    public function clear(): void
    {
        $pattern = $this->prefix . '*';
        $iterator = null;
        do {
            /** @var list<string>|false $keys */
            $keys = $this->redis->scan($iterator, $pattern, 128);
            if ($keys === false) {
                break;
            }
            if ($keys !== []) {
                $this->redis->del($keys);
            }
        } while ($iterator != 0);
    }

    public function remember(string $key, ?int $ttlSeconds, callable $callback): mixed
    {
        $namespaced = $this->namespacedKey($key);
        $raw = $this->redis->get($namespaced);
        if ($raw !== false) {
            try {
                return unserialize((string) $raw, ['allowed_classes' => true]);
            } catch (Throwable) {
                $this->redis->del($namespaced);
            }
        }

        $value = $callback();
        $this->set($key, $value, $ttlSeconds);

        return $value;
    }

    private function namespacedKey(string $key): string
    {
        return $this->prefix . $key;
    }
}
