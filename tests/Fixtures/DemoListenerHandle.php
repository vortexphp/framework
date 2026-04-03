<?php

declare(strict_types=1);

namespace Vortex\Tests\Fixtures;

final class DemoListenerHandle
{
    /** @var list<string> */
    public static array $log = [];

    public static function reset(): void
    {
        self::$log = [];
    }

    public function handle(DemoEvent $event): void
    {
        self::$log[] = 'handle:' . $event->payload;
    }
}
