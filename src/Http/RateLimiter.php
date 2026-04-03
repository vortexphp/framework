<?php

declare(strict_types=1);

namespace Vortex\Http;

use Vortex\Contracts\Cache;

/**
 * Fixed-window request counting using {@see Cache}. Window boundaries align to UNIX epochs
 * (every {@code $decaySeconds} seconds).
 */
final class RateLimiter
{
    public function __construct(
        private readonly Cache $cache,
    ) {
    }

    public function tooManyAttempts(string $baseKey, int $maxAttempts, int $decaySeconds): bool
    {
        $cacheKey = $this->windowedKey($baseKey, $decaySeconds);
        $count = (int) $this->cache->get($cacheKey, 0);

        return $count >= $maxAttempts;
    }

    public function hit(string $baseKey, int $decaySeconds): void
    {
        $now = time();
        $cacheKey = $this->windowedKey($baseKey, $decaySeconds);
        $count = (int) $this->cache->get($cacheKey, 0);
        $ttl = $this->ttlRemaining($now, $decaySeconds);
        $this->cache->set($cacheKey, $count + 1, $ttl);
    }

    /**
     * Seconds until the current fixed window ends (for Retry-After).
     */
    public function availableIn(string $baseKey, int $decaySeconds): int
    {
        return $this->ttlRemaining(time(), $decaySeconds);
    }

    private function windowedKey(string $baseKey, int $decaySeconds): string
    {
        $now = time();
        $windowStart = $now - ($now % $decaySeconds);

        return $baseKey . ':' . $windowStart;
    }

    private function ttlRemaining(int $now, int $decaySeconds): int
    {
        $ttl = $decaySeconds - ($now % $decaySeconds);

        return $ttl < 1 ? $decaySeconds : $ttl;
    }
}
