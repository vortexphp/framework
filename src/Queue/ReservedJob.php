<?php

declare(strict_types=1);

namespace Vortex\Queue;

/**
 * Row reserved by {@see DatabaseQueue::reserve()} for the current worker.
 */
final readonly class ReservedJob
{
    /**
     * @param positive-int $id
     */
    public function __construct(
        public int $id,
        public string $payload,
        public int $attempts,
    ) {
    }
}
