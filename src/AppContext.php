<?php

declare(strict_types=1);

namespace Vortex;

use RuntimeException;

final class AppContext
{
    private static ?Container $container = null;

    public static function set(Container $container): void
    {
        self::$container = $container;
    }

    public static function container(): Container
    {
        if (self::$container === null) {
            throw new RuntimeException('Application context not initialized.');
        }

        return self::$container;
    }
}
