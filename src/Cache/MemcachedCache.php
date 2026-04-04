<?php

declare(strict_types=1);

namespace Vortex\Cache;

use Memcached;
use Throwable;
use Vortex\Contracts\Cache as CacheContract;

/**
 * Memcached-backed cache (ext-memcached). Values are PHP-serialized.
 *
 * {@see self::clear()} bumps an internal generation so prefixed entries are abandoned (they expire via Memcached LRU);
 * it does not issue a global flush.
 */
final class MemcachedCache implements CacheContract
{
    public function __construct(
        private readonly Memcached $memcached,
        private readonly string $prefix,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $raw = $this->memcached->get($this->namespacedKey($key));
        if ($raw === false) {
            return $default;
        }

        try {
            return unserialize((string) $raw, ['allowed_classes' => true]);
        } catch (Throwable) {
            $this->memcached->delete($this->namespacedKey($key));

            return $default;
        }
    }

    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        $payload = serialize($value);
        $k = $this->namespacedKey($key);
        $exp = $ttlSeconds === null ? 0 : max(1, $ttlSeconds);
        if ($this->memcached->set($k, $payload, $exp) === false) {
            throw new \RuntimeException('Memcached set failed for cache key (code ' . (string) $this->memcached->getResultCode() . ').');
        }
    }

    public function add(string $key, mixed $value, int $ttlSeconds): bool
    {
        $payload = serialize($value);
        $k = $this->namespacedKey($key);
        $exp = max(1, $ttlSeconds);

        return $this->memcached->add($k, $payload, $exp);
    }

    public function forget(string $key): void
    {
        $this->memcached->delete($this->namespacedKey($key));
    }

    public function clear(): void
    {
        $meta = $this->metaGenerationKey();
        $this->memcached->add($meta, 0, 0);
        if ($this->memcached->increment($meta) === false) {
            $v = $this->memcached->get($meta);
            $next = ($v === false ? 0 : (int) $v) + 1;
            $this->memcached->set($meta, $next, 0);
        }
    }

    public function remember(string $key, ?int $ttlSeconds, callable $callback): mixed
    {
        $k = $this->namespacedKey($key);
        $raw = $this->memcached->get($k);
        if ($raw !== false) {
            try {
                return unserialize((string) $raw, ['allowed_classes' => true]);
            } catch (Throwable) {
                $this->memcached->delete($k);
            }
        }

        $value = $callback();
        $this->set($key, $value, $ttlSeconds);

        return $value;
    }

    private function metaGenerationKey(): string
    {
        return $this->prefix . '__vortex_gen__';
    }

    private function generation(): int
    {
        $raw = $this->memcached->get($this->metaGenerationKey());

        return $raw === false ? 0 : (int) $raw;
    }

    private function namespacedKey(string $key): string
    {
        return $this->prefix . $this->generation() . ':' . $key;
    }
}
