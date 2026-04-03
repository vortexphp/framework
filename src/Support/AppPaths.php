<?php

declare(strict_types=1);

namespace Vortex\Support;

use InvalidArgumentException;

/**
 * Project path layout. Optional **`config/paths.php`** may return
 * **`['migrations' => '…']`** (relative to project root).
 */
final class AppPaths
{
    private function __construct(
        private readonly string $migrationsRelative,
    ) {
    }

    public static function forBase(string $basePath): self
    {
        $basePath = rtrim($basePath, '/\\');
        $defaults = [
            'migrations' => 'db/migrations',
        ];
        $configFile = $basePath . '/config/paths.php';
        if (is_file($configFile)) {
            /** @var mixed $data */
            $data = require $configFile;
            if (is_array($data)) {
                if (isset($data['migrations']) && is_string($data['migrations']) && trim($data['migrations']) !== '') {
                    $defaults['migrations'] = self::normalizeRelativeKey($data['migrations'], 'migrations');
                }
            }
        }

        return new self($defaults['migrations']);
    }

    private static function normalizeRelativeKey(string $path, string $key): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            throw new InvalidArgumentException("config/paths.php [{$key}] must be a relative path without '..'.");
        }

        return $path;
    }

    public function migrationsDirectory(string $basePath): string
    {
        return rtrim($basePath, '/\\') . '/' . $this->migrationsRelative;
    }

    public function migrationsRelative(): string
    {
        return $this->migrationsRelative;
    }
}
