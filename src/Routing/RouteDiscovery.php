<?php

declare(strict_types=1);

namespace Vortex\Routing;

use Vortex\Console\ConsoleApplication;
use Vortex\Vortex;

final class RouteDiscovery
{
    public static function httpRouteDirectory(string $basePath): string
    {
        return rtrim($basePath, '/') . '/app/Routes';
    }

    /**
     * Load every `app/Routes/*.php` that is not named `*Console.php`. Each file is {@see require}d
     * in sorted order; register routes with {@see Route} (the active router is set before the first file runs).
     */
    public static function loadHttpRoutes(Router $router, string $basePath): void
    {
        Route::useRouter($router);

        $dir = self::httpRouteDirectory($basePath);
        if (! is_dir($dir)) {
            return;
        }

        foreach (self::httpRouteFiles($dir) as $file) {
            require $file;
        }
    }

    /**
     * Load every `app/Routes/*Console.php`. Each file is {@see require}d in sorted order; register commands with
     * {@see \Vortex\Vortex}; the active {@see ConsoleApplication} is set before the first file runs.
     */
    public static function loadConsoleRoutes(ConsoleApplication $app, string $basePath): void
    {
        $dir = self::httpRouteDirectory($basePath);
        if (! is_dir($dir)) {
            return;
        }

        Vortex::bindConsoleApplication($app);
        try {
            foreach (self::consoleRouteFiles($dir) as $file) {
                require $file;
            }
        } finally {
            Vortex::bindConsoleApplication(null);
        }
    }

    /**
     * @return list<string>
     */
    private static function httpRouteFiles(string $dir): array
    {
        $paths = glob($dir . '/*.php') ?: [];
        $out = [];
        foreach ($paths as $path) {
            if (str_ends_with(basename($path), 'Console.php')) {
                continue;
            }
            $out[] = $path;
        }
        sort($out);

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function consoleRouteFiles(string $dir): array
    {
        $paths = glob($dir . '/*Console.php') ?: [];
        sort($paths);

        return $paths;
    }
}
