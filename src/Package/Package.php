<?php

declare(strict_types=1);

namespace Vortex\Package;

use Vortex\Console\ConsoleApplication;
use Vortex\Container;

/**
 * Application extension point (composer packages). Override only the hooks you need.
 *
 * Register concrete classes in config/app.php (`packages` key; reads as `app.packages` on the config repository).
 */
abstract class Package
{
    /**
     * Early bindings (container, aliases). Runs once HTTP stack has {@see Repository} loaded,
     * before core services that are lazily resolved may be built.
     */
    public function register(Container $container, string $basePath): void
    {
    }

    /**
     * After HTTP routes from routes/*.php are loaded; {@see \Vortex\Routing\Route} is bound to the app router.
     */
    public function boot(Container $container, string $basePath): void
    {
    }

    /**
     * While the CLI kernel is registering commands (same window as routes/console.php).
     */
    public function console(ConsoleApplication $app, string $basePath): void
    {
    }
}
