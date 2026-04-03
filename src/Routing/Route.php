<?php

declare(strict_types=1);

namespace Vortex\Routing;

use Closure;
use RuntimeException;

/**
 * Static entry point for route files. {@see \Vortex\Application::boot()} binds the active {@see Router}
 * with {@see self::useRouter()} before {@see RouteDiscovery::loadHttpRoutes()} runs—same idea as a
 * facade, without a global service locator for the rest of the app.
 */
final class Route
{
    private static ?Router $router = null;

    public static function useRouter(Router $router): void
    {
        self::$router = $router;
    }

    /**
     * @param Closure|array{0: class-string, 1: string} $action
     * @param list<string> $middleware
     */
    public static function get(string $pattern, Closure|array $action, array $middleware = []): Router
    {
        return self::router()->get($pattern, $action, $middleware);
    }

    /**
     * @param Closure|array{0: class-string, 1: string} $action
     * @param list<string> $middleware
     */
    public static function post(string $pattern, Closure|array $action, array $middleware = []): Router
    {
        return self::router()->post($pattern, $action, $middleware);
    }

    /**
     * @param list<string> $methods
     * @param Closure|array{0: class-string, 1: string} $action
     * @param list<string> $middleware
     */
    public static function add(array $methods, string $pattern, Closure|array $action, array $middleware = []): Router
    {
        return self::router()->add($methods, $pattern, $action, $middleware);
    }

    public static function name(string $name): Router
    {
        return self::router()->name($name);
    }

    private static function router(): Router
    {
        if (self::$router === null) {
            throw new RuntimeException(
                'Route::get() (or post/add) was called before Route::useRouter() (e.g. during Application::boot() route discovery).',
            );
        }

        return self::$router;
    }
}
