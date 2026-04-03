<?php

declare(strict_types=1);

namespace Vortex\Tests\Fixtures;

final class DemoListenerSecond
{
    public function handle(DemoEvent $event): void
    {
        DemoListenerHandle::$log[] = 'second:' . $event->payload;
    }
}
