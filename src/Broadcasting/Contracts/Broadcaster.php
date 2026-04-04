<?php

declare(strict_types=1);

namespace Vortex\Broadcasting\Contracts;

/**
 * Dispatch an event on a logical channel (same PHP process by default via {@see \Vortex\Broadcasting\SyncBroadcaster};
 * replace the binding for Redis, SSE bridges, etc.).
 */
interface Broadcaster
{
    /**
     * @param array<string, mixed> $payload Must be JSON-serializable if sent over the wire elsewhere.
     */
    public function publish(string $channel, string $event, array $payload = []): void;
}
