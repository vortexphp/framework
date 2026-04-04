<?php

declare(strict_types=1);

namespace Vortex\Queue\Contracts;

use Vortex\Queue\ReservedJob;

interface QueueDriver
{
    public function push(string $queue, Job $job, int $delaySeconds = 0): void;

    public function pushSerialized(string $queue, string $serializedPayload, int $delaySeconds = 0): void;

    /**
     * @param positive-int $staleReserveSeconds used by the database driver for abandoned reservations; ignored by the Redis list POP driver
     */
    public function reserve(string $queue, int $staleReserveSeconds): ?ReservedJob;

    public function delete(ReservedJob $reserved): void;

    /**
     * @param non-negative-int $attempts
     * @param non-negative-int $delaySeconds
     */
    public function release(ReservedJob $reserved, int $attempts, int $delaySeconds): void;
}
