<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Cache\FileCache;
use Vortex\Http\RateLimiter;

final class RateLimiterTest extends TestCase
{
    public function testAllowsUpToMaxThenBlocks(): void
    {
        $dir = sys_get_temp_dir() . '/vortex-rl-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($dir, 0777, true));

        try {
            $cache = new FileCache($dir, 'rl:');
            $limiter = new RateLimiter($cache);
            $base = 'client:a';
            $max = 3;
            $decay = 3600;

            self::assertFalse($limiter->tooManyAttempts($base, $max, $decay));
            $limiter->hit($base, $decay);
            self::assertFalse($limiter->tooManyAttempts($base, $max, $decay));
            $limiter->hit($base, $decay);
            self::assertFalse($limiter->tooManyAttempts($base, $max, $decay));
            $limiter->hit($base, $decay);
            self::assertTrue($limiter->tooManyAttempts($base, $max, $decay));
        } finally {
            $cache = new FileCache($dir, 'rl:');
            $cache->clear();
            foreach (glob($dir . '/*') ?: [] as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
            @rmdir($dir);
        }
    }

    public function testAvailableInIsPositive(): void
    {
        $dir = sys_get_temp_dir() . '/vortex-rl2-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($dir, 0777, true));

        try {
            $cache = new FileCache($dir, 'rl2:');
            $limiter = new RateLimiter($cache);
            $n = $limiter->availableIn('x', 60);
            self::assertGreaterThan(0, $n);
            self::assertLessThanOrEqual(60, $n);
        } finally {
            (new FileCache($dir, 'rl2:'))->clear();
            foreach (glob($dir . '/*') ?: [] as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
            @rmdir($dir);
        }
    }
}
