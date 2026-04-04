<?php

declare(strict_types=1);

namespace Vortex\Schedule;

/**
 * @param class-string $handlerClass Resolved from the container; must define __invoke() or handle(): void.
 */
final readonly class ScheduledTask
{
    public function __construct(
        public string $cron,
        public string $handlerClass,
    ) {
    }
}
