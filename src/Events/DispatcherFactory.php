<?php

declare(strict_types=1);

namespace Vortex\Events;

use Vortex\Config\Repository;
use Vortex\Container;

final class DispatcherFactory
{
    public static function make(Container $container): Dispatcher
    {
        $d = new Dispatcher($container);
        $map = Repository::get('events.listen', []);
        if (! is_array($map)) {
            return $d;
        }
        foreach ($map as $eventClass => $listeners) {
            if (! is_string($eventClass)) {
                continue;
            }
            $list = is_array($listeners) ? $listeners : [$listeners];
            foreach ($list as $listener) {
                if (is_string($listener)) {
                    $d->listen($eventClass, $listener);
                }
            }
        }

        return $d;
    }
}
