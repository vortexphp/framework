<?php

declare(strict_types=1);

namespace Vortex\Queue;

/**
 * Job claimed by {@see DatabaseQueue::reserve()} or {@see RedisQueue::reserve()}.
 */
final readonly class ReservedJob
{
    /**
     * @param positive-int $id log identifier (SQL row id or Redis sequence)
     */
    public function __construct(
        public int $id,
        public string $payload,
        public int $attempts,
        public string $queue = '',
    ) {
    }
}
