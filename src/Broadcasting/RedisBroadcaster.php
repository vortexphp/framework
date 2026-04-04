<?php

declare(strict_types=1);

namespace Vortex\Broadcasting;

use Redis;
use Vortex\Broadcasting\Contracts\Broadcaster;
use Vortex\Support\JsonHelp;

/**
 * Runs {@see SyncBroadcaster} in-process, then {@see Redis::publish()} for cross-process fan-out.
 * Wire {@see SyncBroadcaster} from the container for {@see SyncBroadcaster::listen()} in the same app.
 * Remote consumers use Redis SUBSCRIBE on **`{$prefix}{$channel}`** with JSON messages **`{"event","payload"}`**.
 */
final class RedisBroadcaster implements Broadcaster
{
    public function __construct(
        private Redis $redis,
        private string $prefix,
        private SyncBroadcaster $local,
    ) {
    }

    public function local(): SyncBroadcaster
    {
        return $this->local;
    }

    public function publish(string $channel, string $event, array $payload = []): void
    {
        $this->local->publish($channel, $event, $payload);
        $this->redis->publish(
            $this->prefix . $channel,
            JsonHelp::encode(['event' => $event, 'payload' => $payload]),
        );
    }
}
