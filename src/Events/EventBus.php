<?php

declare(strict_types=1);

namespace Vortex\Events;

use Vortex\AppContext;

/**
 * Static entry to the singleton {@see Dispatcher} (same instance as constructor injection).
 */
final class EventBus
{
    public static function dispatch(object $event): void
    {
        AppContext::container()->make(Dispatcher::class)->dispatch($event);
    }
}
