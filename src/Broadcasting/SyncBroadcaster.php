<?php

declare(strict_types=1);

namespace Vortex\Broadcasting;

use Closure;
use Vortex\Broadcasting\Contracts\Broadcaster;

/**
 * In-process pub/sub: {@see listen()} registers callbacks; {@see publish()} invokes every listener on that channel.
 */
final class SyncBroadcaster implements Broadcaster
{
    /** @var array<string, list<Closure>> */
    private array $listeners = [];

    /**
     * @param Closure(string, array<string, mixed>): void $callback
     */
    public function listen(string $channel, Closure $callback): void
    {
        $this->listeners[$channel][] = $callback;
    }

    public function publish(string $channel, string $event, array $payload = []): void
    {
        foreach ($this->listeners[$channel] ?? [] as $listener) {
            $listener($event, $payload);
        }
    }

    /**
     * @internal Test harness.
     */
    public function forgetAll(): void
    {
        $this->listeners = [];
    }
}
