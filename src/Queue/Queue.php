<?php

declare(strict_types=1);

namespace Vortex\Queue;

use RuntimeException;
use Vortex\AppContext;
use Vortex\Config\Repository;
use Vortex\Queue\Contracts\Job;
use Vortex\Queue\Contracts\QueueDriver;

/**
 * Static entry to the configured {@see QueueDriver}.
 */
final class Queue
{
    public static function push(Job $job, ?string $queue = null, int $delaySeconds = 0): void
    {
        $name = $queue ?? self::defaultQueue();
        self::driver()->push($name, $job, $delaySeconds);
    }

    public static function defaultQueue(): string
    {
        $v = Repository::get('queue.default', 'default');

        return is_string($v) && $v !== '' ? $v : 'default';
    }

    public static function driver(): QueueDriver
    {
        try {
            return AppContext::container()->make(QueueDriver::class);
        } catch (RuntimeException) {
            throw new RuntimeException('Application context is not initialized; call Queue::push only after Application::boot().');
        }
    }
}
