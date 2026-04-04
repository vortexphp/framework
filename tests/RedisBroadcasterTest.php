<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Redis;
use Vortex\Broadcasting\RedisBroadcaster;
use Vortex\Broadcasting\SyncBroadcaster;

final class RedisBroadcasterTest extends TestCase
{
    public function testPublishInvokesLocalThenRedis(): void
    {
        if (! class_exists(Redis::class)) {
            self::markTestSkipped('ext-redis required for this test double target');
        }

        $seen = [];
        $local = new SyncBroadcaster();
        $local->listen('ch', static function (string $event, array $payload) use (&$seen): void {
            $seen[] = [$event, $payload];
        });

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())
            ->method('publish')
            ->with(
                'pfx:ch',
                '{"event":"e","payload":{"k":1}}',
            )
            ->willReturn(1);

        $b = new RedisBroadcaster($redis, 'pfx:', $local);
        $b->publish('ch', 'e', ['k' => 1]);

        self::assertSame([['e', ['k' => 1]]], $seen);
    }

    public function testLocalAccessorReturnsWrappedSync(): void
    {
        if (! class_exists(Redis::class)) {
            self::markTestSkipped('ext-redis required');
        }

        $local = new SyncBroadcaster();
        $redis = $this->createMock(Redis::class);
        $redis->method('publish')->willReturn(0);

        $b = new RedisBroadcaster($redis, 'x:', $local);
        self::assertSame($local, $b->local());
    }
}
