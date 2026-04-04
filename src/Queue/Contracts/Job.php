<?php

declare(strict_types=1);

namespace Vortex\Queue\Contracts;

/**
 * Serializable job executed by a queue worker ({@see \Vortex\Queue\DatabaseQueue}).
 */
interface Job
{
    public function handle(): void;
}
