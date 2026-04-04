<?php

declare(strict_types=1);

namespace Vortex\Tests;

use InvalidArgumentException;
use Memcached;
use Mockery;
use PHPUnit\Framework\TestCase;
use Vortex\Cache\CacheManager;
use Vortex\Cache\MemcachedCache;

final class MemcachedCacheTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testManagerThrowsWhenMemcachedExtensionMissing(): void
    {
        if (class_exists(Memcached::class)) {
            self::markTestSkipped('ext-memcached is loaded; cannot assert missing extension.');
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ext-memcached');

        CacheManager::fromConfig(sys_get_temp_dir(), [
            'default' => 'mc',
            'stores' => [
                'mc' => ['driver' => 'memcached', 'host' => '127.0.0.1', 'prefix' => 't:'],
            ],
        ])->store();
    }

    public function testGetSetForget(): void
    {
        if (! class_exists(Memcached::class)) {
            self::markTestSkipped('ext-memcached not available.');
        }

        $meta = 'pre:__vortex_gen__';
        $mc = Mockery::mock(Memcached::class);
        $mc->shouldReceive('get')->with($meta)->andReturn(false);
        $mc->shouldReceive('get')->with('pre:0:foo')->twice()->andReturn(false, serialize(42));
        $mc->shouldReceive('set')->with('pre:0:foo', serialize(42), 0)->once()->andReturn(true);
        $mc->shouldReceive('delete')->with('pre:0:foo')->once()->andReturn(true);

        $cache = new MemcachedCache($mc, 'pre:');

        self::assertSame(9, $cache->get('foo', 9));
        $cache->set('foo', 42);
        self::assertSame(42, $cache->get('foo'));
        $cache->forget('foo');
    }

    public function testAddUsesMemcachedAdd(): void
    {
        if (! class_exists(Memcached::class)) {
            self::markTestSkipped('ext-memcached not available.');
        }

        $meta = 'p:__vortex_gen__';
        $mc = Mockery::mock(Memcached::class);
        $mc->shouldReceive('get')->with($meta)->andReturn(false);
        $mc->shouldReceive('add')->with('p:0:lock', serialize(1), 120)->once()->andReturn(true);

        $cache = new MemcachedCache($mc, 'p:');
        self::assertTrue($cache->add('lock', 1, 120));
    }

    public function testClearIncrementsGenerationKey(): void
    {
        if (! class_exists(Memcached::class)) {
            self::markTestSkipped('ext-memcached not available.');
        }

        $this->expectNotToPerformAssertions();

        $meta = 'pre:__vortex_gen__';
        $mc = Mockery::mock(Memcached::class);
        $mc->shouldReceive('add')->with($meta, 0, 0)->once()->andReturn(true);
        $mc->shouldReceive('increment')->with($meta)->once()->andReturn(1);

        $cache = new MemcachedCache($mc, 'pre:');
        $cache->clear();
    }

    public function testRemember(): void
    {
        if (! class_exists(Memcached::class)) {
            self::markTestSkipped('ext-memcached not available.');
        }

        $meta = 'pre:__vortex_gen__';
        $mc = Mockery::mock(Memcached::class);
        $mc->shouldReceive('get')->with($meta)->andReturn(false);
        $mc->shouldReceive('get')->with('pre:0:k')->once()->andReturn(false);
        $mc->shouldReceive('set')->with('pre:0:k', serialize(5), 10)->once()->andReturn(true);

        $cache = new MemcachedCache($mc, 'pre:');
        $n = 0;
        self::assertSame(5, $cache->remember('k', 10, static function () use (&$n): int {
            $n++;

            return 5;
        }));
        self::assertSame(1, $n);
    }
}
