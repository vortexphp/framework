<?php

declare(strict_types=1);

namespace Vortex\Package;

use InvalidArgumentException;
use Vortex\Config\Repository;
use Vortex\Console\ConsoleApplication;
use Vortex\Container;

final class PackageRegistry
{
    /**
     * @return list<class-string<Package>>
     */
    public static function classes(string $basePath): array
    {
        $packages = Repository::get('app.packages', []);
        if (! is_array($packages)) {
            return [];
        }

        $out = [];
        foreach ($packages as $class) {
            if (! is_string($class) || $class === '' || ! class_exists($class)) {
                continue;
            }

            $out[] = $class;
        }

        return $out;
    }

    public static function register(Container $container, string $basePath): void
    {
        $basePath = rtrim($basePath, '/');
        foreach (self::classes($basePath) as $class) {
            self::instantiate($class)->register($container, $basePath);
        }
    }

    public static function boot(Container $container, string $basePath): void
    {
        $basePath = rtrim($basePath, '/');
        foreach (self::classes($basePath) as $class) {
            self::instantiate($class)->boot($container, $basePath);
        }
    }

    public static function dispatchConsole(ConsoleApplication $app, string $basePath): void
    {
        $basePath = rtrim($basePath, '/');
        foreach (self::classes($basePath) as $class) {
            self::instantiate($class)->console($app, $basePath);
        }
    }

    /**
     * @param class-string $class
     */
    private static function instantiate(string $class): Package
    {
        $instance = new $class();
        if (! $instance instanceof Package) {
            throw new InvalidArgumentException(
                "Config app.packages entry {$class} must extend " . Package::class,
            );
        }

        return $instance;
    }
}
