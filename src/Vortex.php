<?php

declare(strict_types=1);

namespace Vortex;

use RuntimeException;
use Vortex\Console\Command;
use Vortex\Console\ConsoleApplication;

/**
 * Application-facing facade. Use {@see self::command()} from `app/Routes/*Console.php` (same idea as {@see \Vortex\Routing\Route} for HTTP).
 */
final class Vortex
{
    private static ?ConsoleApplication $consoleApplication = null;

    /**
     * @internal Called by {@see \Vortex\Routing\RouteDiscovery::loadConsoleRoutes()} while requiring console route files.
     */
    public static function bindConsoleApplication(?ConsoleApplication $application): void
    {
        self::$consoleApplication = $application;
    }

    /**
     * @param class-string<Command>|Command $command
     */
    public static function command(string|Command $command): void
    {
        $app = self::$consoleApplication ?? throw new RuntimeException(
            'app/Routes/*Console.php files can only register commands while the CLI kernel is loading them (no active ConsoleApplication).',
        );

        $instance = is_string($command) ? new $command() : $command;
        $app->register($instance);
    }
}
