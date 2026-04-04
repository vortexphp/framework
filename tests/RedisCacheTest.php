<?php

declare(strict_types=1);

namespace Vortex\Tests;

use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;
use Vortex\Cache\CacheManager;
use Vortex\Cache\RedisCache;
use Redis;

final class RedisCacheTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testManagerThrowsWhenRedisExtensionMissing(): void
    {
        if (class_exists(Redis::class)) {
            self::markTestSkipped('phpredis is loaded; cannot assert missing extension.');
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('phpredis extension');

        CacheManager::fromConfig(sys_get_temp_dir(), [
            'default' => 'redis',
            'stores' => [
                'redis' => [
                    'driver' => 'redis',
                    'host' => '127.0.0.1',
                    'prefix' => 'test:',
                ],
            ],
        ])->store();
    }

    public function testGetSetForget(): void
    {
        if (! class_exists(Redis::class)) {
            self::markTestSkipped('phpredis not available.');
        }

        $redis = Mockery::mock(Redis::class);
        $redis->shouldReceive('get')->twice()->with('pre:foo')->andReturn(false, serialize(42));
        $redis->shouldReceive('set')->with('pre:foo', serialize(42))->once()->andReturn(true);
        $redis->shouldReceive('del')->with('pre:foo')->once()->andReturn(1);

        $cache = new RedisCache($redis, 'pre:');

        self::assertSame(7, $cache->get('foo', 7));
        $cache->set('foo', 42);
        self::assertSame(42, $cache->get('foo'));
        $cache->forget('foo');
    }

    public function testSetWithTtlUsesSetex(): void
    {
        if (! class_exists(Redis::class)) {
            self::markTestSkipped('phpredis not available.');
        }

        $this->expectNotToPerformAssertions();

        $redis = Mockery::mock(Redis::class);
        $redis->shouldReceive('setex')->with('pre:x', 60, serialize('ok'))->once()->andReturn(true);

        $cache = new RedisCache($redis, 'pre:');
        $cache->set('x', 'ok', 60);
    }

    public function testRemember(): void
    {
        if (! class_exists(Redis::class)) {
            self::markTestSkipped('phpredis not available.');
        }

        $redis = Mockery::mock(Redis::class);
        $redis->shouldReceive('get')->once()->with('p:k')->andReturn(false);
        $redis->shouldReceive('setex')->once()->with('p:k', 10, serialize(5))->andReturn(true);

        $cache = new RedisCache($redis, 'p:');
        $n = 0;
        self::assertSame(5, $cache->remember('k', 10, static function () use (&$n): int {
            $n++;

            return 5;
        }));
        self::assertSame(1, $n);
    }

    public function testClearUsesScanAndDel(): void
    {
        if (! class_exists(Redis::class)) {
            self::markTestSkipped('phpredis not available.');
        }

        $this->expectNotToPerformAssertions();

        $redis = Mockery::mock(Redis::class);
        $redis->shouldReceive('scan')
            ->once()
            ->andReturnUsing(static function (&$it): array {
                $it = 0;

                return ['pre:a', 'pre:b'];
            });
        $redis->shouldReceive('del')->once()->with(['pre:a', 'pre:b'])->andReturn(2);

        $cache = new RedisCache($redis, 'pre:');
        $cache->clear();
    }
}
