<?php

declare(strict_types=1);

namespace Vortex\Tests\Fixtures;

final class DemoListenerInvoke
{
    /** @var list<string> */
    public static array $log = [];

    public static function reset(): void
    {
        self::$log = [];
    }

    public function __invoke(DemoEvent $event): void
    {
        self::$log[] = 'invoke:' . $event->payload;
    }
}
