<?php

declare(strict_types=1);

namespace Vortex\Package;

use InvalidArgumentException;
use ReflectionClass;
use RuntimeException;
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
     * Copy {@see Package::publicAssets()} for every configured package into {@code public/}.
     *
     * @return list<string> Status lines for the CLI (published paths and skips).
     */
    public static function publishPublicAssets(string $basePath): array
    {
        $basePath = rtrim($basePath, '/');
        $publicRoot = $basePath . '/public';
        $lines = [];
        foreach (self::classes($basePath) as $class) {
            $package = self::instantiate($class);
            $packageRoot = self::packageRootContainingClass($class);
            foreach ($package->publicAssets() as $from => $to) {
                if (! is_string($from) || ! is_string($to)) {
                    continue;
                }
                $from = self::normalizeRelativePath($from);
                $to = self::normalizeRelativePath($to);
                if ($from === null || $to === null) {
                    $lines[] = "skip unsafe path for {$class}";
                    continue;
                }
                $src = $packageRoot . '/' . $from;
                $dst = $publicRoot . '/' . $to;
                if (! is_file($src)) {
                    $lines[] = "missing {$class}: {$from}";

                    continue;
                }
                $dir = dirname($dst);
                if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
                    $lines[] = "could not mkdir {$dir}";

                    continue;
                }
                if (copy($src, $dst) === true) {
                    $lines[] = "published public/{$to} <- {$class}";
                } else {
                    $lines[] = "copy failed public/{$to} <- {$class}";
                }
            }
        }

        return $lines;
    }

    /**
     * @param class-string $class
     */
    public static function packageRootContainingClass(string $class): string
    {
        $file = (new ReflectionClass($class))->getFileName();
        if ($file === false) {
            throw new RuntimeException("Could not reflect package class {$class}.");
        }
        $dir = dirname($file);
        for ($i = 0; $i < 16; $i++) {
            if (is_file($dir . '/composer.json')) {
                return $dir;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        throw new RuntimeException(
            "Could not find package root (composer.json) for class {$class}.",
        );
    }

    private static function normalizeRelativePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return $path;
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
