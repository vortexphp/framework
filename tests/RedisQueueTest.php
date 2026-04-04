<?php

declare(strict_types=1);

namespace Vortex\Tests;

use Mockery;
use PHPUnit\Framework\TestCase;
use Redis;
use Vortex\Queue\Contracts\Job;
use Vortex\Queue\RedisQueue;

final class RedisQueueTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testReservePopsReadyList(): void
    {
        if (! class_exists(Redis::class)) {
            self::markTestSkipped('phpredis not available.');
        }

        $env = json_encode(['b' => base64_encode(serialize(new RedisQueueCountingJob(3))), 'a' => 0], JSON_THROW_ON_ERROR);
        $redis = Mockery::mock(Redis::class);
        $redis->shouldReceive('zRangeByScore')->andReturn([]);
        $redis->shouldReceive('lPop')->with('pre:ready:default')->once()->andReturn($env);
        $redis->shouldReceive('incr')->with('pre:seq')->once()->andReturn(7);

        $q = new RedisQueue($redis, 'pre:');
        $r = $q->reserve('default', 60);
        self::assertNotNull($r);
        self::assertSame(7, $r->id);
        self::assertSame('default', $r->queue);
        self::assertSame(0, $r->attempts);
        $job = unserialize($r->payload, ['allowed_classes' => true]);
        self::assertInstanceOf(RedisQueueCountingJob::class, $job);
    }

    public function testReleasePushesBackWithDelay(): void
    {
        if (! class_exists(Redis::class)) {
            self::markTestSkipped('phpredis not available.');
        }

        $this->expectNotToPerformAssertions();

        $redis = Mockery::mock(Redis::class);
        $redis->shouldReceive('set')->once()->andReturn(true);
        $redis->shouldReceive('zAdd')->once()->with('pre:delayed:emails', Mockery::type('float'), Mockery::type('string'))->andReturn(1);

        $q = new RedisQueue($redis, 'pre:');
        $reserved = new \Vortex\Queue\ReservedJob(1, serialize(new RedisQueueCountingJob(1)), 0, 'emails');
        $q->release($reserved, 2, 30);
    }
}

final class RedisQueueCountingJob implements Job
{
    public function __construct(
        private readonly int $n,
    ) {
    }

    public function handle(): void
    {
    }
}
