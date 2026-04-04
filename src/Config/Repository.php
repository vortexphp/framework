<?php

declare(strict_types=1);

namespace Vortex\Config;

final class Repository
{
    private static ?self $instance = null;

    /** @var array<string, mixed> */
    private array $items = [];

    public function __construct(string $configPath)
    {
        if (! is_dir($configPath)) {
            return;
        }

        foreach (glob($configPath . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            /** @var mixed $data */
            $data = require $file;
            if (is_array($data)) {
                $this->items[$name] = $data;
            }
        }
    }

    public static function setInstance(self $repository): void
    {
        self::$instance = $repository;
    }

    public static function forgetInstance(): void
    {
        self::$instance = null;
    }

    public static function initialized(): bool
    {
        return self::$instance !== null;
    }

    private static function resolved(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Repository is not initialized; call Repository::setInstance() during Application::boot().');
        }

        return self::$instance;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::resolved()->fetch($key, $default);
    }

    public static function has(string $key): bool
    {
        return self::resolved()->contains($key);
    }

    private function fetch(string $key, mixed $default = null): mixed
    {
        if (! str_contains($key, '.')) {
            return $this->items[$key] ?? $default;
        }

        $segments = explode('.', $key);
        $value = $this->items[array_shift($segments)] ?? null;
        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    private function contains(string $key): bool
    {
        return $this->fetch($key, $this) !== $this;
    }
}
