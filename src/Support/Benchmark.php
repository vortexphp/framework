<?php

declare(strict_types=1);

namespace Vortex\Support;

use InvalidArgumentException;

/**
 * Lightweight in-process benchmarks backed by monotonic time.
 */
final class Benchmark
{
    /**
     * @var array<string, int>
     */
    private static array $startedAtNs = [];

    public static function start(string $name = 'default'): void
    {
        self::$startedAtNs[$name] = hrtime(true);
    }

    public static function has(string $name = 'default'): bool
    {
        return array_key_exists($name, self::$startedAtNs);
    }

    public static function elapsedNs(string $name = 'default'): int
    {
        if (! self::has($name)) {
            throw new InvalidArgumentException('Benchmark not started: ' . $name);
        }

        return hrtime(true) - self::$startedAtNs[$name];
    }

    public static function elapsedMs(string $name = 'default', int $precision = 3): float
    {
        return round(self::elapsedNs($name) / 1_000_000, $precision);
    }

    public static function elapsedSeconds(string $name = 'default', int $precision = 6): float
    {
        return round(self::elapsedNs($name) / 1_000_000_000, $precision);
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return array{result: T, elapsed_ms: float}
     */
    public static function measure(callable $callback, int $precision = 3): array
    {
        $started = hrtime(true);
        $result = $callback();
        $elapsedMs = round((hrtime(true) - $started) / 1_000_000, $precision);

        return [
            'result' => $result,
            'elapsed_ms' => $elapsedMs,
        ];
    }

    public static function forget(?string $name = null): void
    {
        if ($name === null) {
            self::$startedAtNs = [];

            return;
        }

        unset(self::$startedAtNs[$name]);
    }
}
