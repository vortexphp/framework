<?php

declare(strict_types=1);

namespace Vortex\Support;

use InvalidArgumentException;

/**
 * Project path layout. Optional **`config/paths.php`** may return
 * **`['migrations' => '…', 'models' => '…', 'controllers' => '…', 'commands' => '…']`** (paths relative to project root).
 * Command and controller namespaces are derived for paths under **`app/`** (PSR-4 **`App\` → `app/`**).
 */
final class AppPaths
{
    private function __construct(
        private readonly string $migrationsRelative,
        private readonly string $modelsRelative,
        private readonly string $controllersRelative,
        private readonly string $commandsRelative,
    ) {
    }

    public static function forBase(string $basePath): self
    {
        $basePath = rtrim($basePath, '/\\');
        $defaults = [
            'migrations' => 'database/migrations',
            'models' => 'app/Models',
            'controllers' => 'app/Controllers',
            'commands' => 'app/Commands',
        ];
        $configFile = $basePath . '/config/paths.php';
        if (is_file($configFile)) {
            /** @var mixed $data */
            $data = require $configFile;
            if (is_array($data)) {
                if (isset($data['migrations']) && is_string($data['migrations']) && trim($data['migrations']) !== '') {
                    $defaults['migrations'] = self::normalizeRelativeKey($data['migrations'], 'migrations');
                }
                if (isset($data['models']) && is_string($data['models']) && trim($data['models']) !== '') {
                    $defaults['models'] = self::normalizeRelativeKey($data['models'], 'models');
                }
                if (isset($data['controllers']) && is_string($data['controllers']) && trim($data['controllers']) !== '') {
                    $defaults['controllers'] = self::normalizeRelativeKey($data['controllers'], 'controllers');
                }
                if (isset($data['commands']) && is_string($data['commands']) && trim($data['commands']) !== '') {
                    $defaults['commands'] = self::normalizeRelativeKey($data['commands'], 'commands');
                }
            }
        }

        return new self($defaults['migrations'], $defaults['models'], $defaults['controllers'], $defaults['commands']);
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

    public function modelsDirectory(string $basePath): string
    {
        return rtrim($basePath, '/\\') . '/' . $this->modelsRelative;
    }

    public function modelsRelative(): string
    {
        return $this->modelsRelative;
    }

    public function controllersDirectory(string $basePath): string
    {
        return rtrim($basePath, '/\\') . '/' . $this->controllersRelative;
    }

    public function controllersRelative(): string
    {
        return $this->controllersRelative;
    }

    public function commandsDirectory(string $basePath): string
    {
        return rtrim($basePath, '/\\') . '/' . $this->commandsRelative;
    }

    public function commandsRelative(): string
    {
        return $this->commandsRelative;
    }

    /**
     * PHP namespace for classes in {@see self::controllersDirectory()} (e.g. `app/Controllers` → `App\Controllers`).
     *
     * @throws InvalidArgumentException when the configured path is not under `app/`
     */
    public function controllersNamespace(): string
    {
        return self::psr4NamespaceFromAppRelative($this->controllersRelative, 'controllers');
    }

    /**
     * PHP namespace for classes in {@see self::commandsDirectory()} (e.g. `app/Commands` → `App\Commands`).
     *
     * @throws InvalidArgumentException when the configured path is not under `app/`
     */
    public function commandsNamespace(): string
    {
        return self::psr4NamespaceFromAppRelative($this->commandsRelative, 'commands');
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function psr4NamespaceFromAppRelative(string $relative, string $configKey): string
    {
        $relative = str_replace('\\', '/', trim($relative, '/'));
        if (! str_starts_with($relative, 'app/')) {
            throw new InvalidArgumentException(
                "config/paths.php [{$configKey}] must be under app/ for PSR-4 (App\\ → app/). Got: {$relative}",
            );
        }

        $rest = substr($relative, strlen('app/'));
        $parts = array_values(array_filter(explode('/', $rest), static fn (string $p): bool => $p !== ''));
        if ($parts === []) {
            throw new InvalidArgumentException("config/paths.php [{$configKey}] is not a valid app/ subpath.");
        }

        return 'App\\' . implode('\\', $parts);
    }
}
