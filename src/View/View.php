<?php

declare(strict_types=1);

namespace Vortex\View;

use Vortex\Http\Response;

/**
 * Facade for template rendering. Backed by the container-registered {@see Factory}.
 */
final class View
{
    private static ?Factory $factory = null;

    public static function useFactory(Factory $factory): void
    {
        self::$factory = $factory;
    }

    public static function forgetFactory(): void
    {
        self::$factory = null;
    }

    private static function engine(): Factory
    {
        if (self::$factory === null) {
            throw new \RuntimeException('View is not configured; call View::useFactory() from bootstrap.');
        }

        return self::$factory;
    }

    public static function share(string $key, mixed $value): void
    {
        self::engine()->share($key, $value);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $name, array $data = []): string
    {
        return self::engine()->render($name, $data);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|string[]> $headers
     */
    public static function html(string $name, array $data = [], int $status = 200, array $headers = []): Response
    {
        return self::engine()->html($name, $data, $status, $headers);
    }
}
