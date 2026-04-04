<?php

declare(strict_types=1);

namespace Vortex\Tests;

use DateInterval;
use PHPUnit\Framework\TestCase;
use Vortex\Cache\FileCache;
use Vortex\Cache\NullCache;
use Vortex\Cache\Psr16Cache;
use Vortex\Cache\SimpleCacheInvalidArgumentException;

final class Psr16CacheTest extends TestCase
{
    public function testEmptyKeyThrows(): void
    {
        $psr = new Psr16Cache(new NullCache());
        $this->expectException(SimpleCacheInvalidArgumentException::class);
        $psr->get('');
    }

    public function testRoundTripWithFileDriver(): void
    {
        $dir = sys_get_temp_dir() . '/vortex-psr16-' . bin2hex(random_bytes(4));
        self::assertTrue(mkdir($dir, 0700, true));
        try {
            $psr = new Psr16Cache(new FileCache($dir, 't'));
            self::assertTrue($psr->set('k1', ['a' => 1], 60));
            self::assertTrue($psr->has('k1'));
            self::assertSame(['a' => 1], $psr->get('k1'));
            self::assertSame(['k1' => ['a' => 1], 'missing' => null], [...$psr->getMultiple(['k1', 'missing'], null)]);
            self::assertTrue($psr->delete('k1'));
            self::assertFalse($psr->has('k1'));
            self::assertTrue($psr->setMultiple(['a' => 1, 'b' => 2], new DateInterval('PT60S')));
            self::assertSame(1, $psr->get('a'));
            self::assertTrue($psr->deleteMultiple(['a', 'b']));
            self::assertTrue($psr->clear());
        } finally {
            foreach (glob($dir . '/*.cache') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }

    public function testHasWithNullCacheAlwaysMiss(): void
    {
        $psr = new Psr16Cache(new NullCache());
        self::assertFalse($psr->has('any'));
    }
}
