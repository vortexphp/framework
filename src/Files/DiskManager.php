<?php

declare(strict_types=1);

namespace Vortex\Files;

use InvalidArgumentException;
use Vortex\Config\Repository;
use Vortex\Contracts\Filesystem;

/**
 * Resolves named disks from {@code config/storage.php} (or built-in defaults). Drivers are constructed lazily
 * unless built with {@see fromInstances()} for tests.
 */
final class DiskManager
{
    /** @var array<string, Filesystem> */
    private array $lazyResolved = [];

    /**
     * @param array<string, array<string, mixed>>|null $diskConfigs
     * @param array<string, Filesystem>|null $eagerDisks
     */
    private function __construct(
        private readonly string $defaultDisk,
        private readonly ?array $diskConfigs,
        private readonly string $basePath,
        private readonly ?array $eagerDisks = null,
    ) {
    }

    /**
     * @param array<string, Filesystem> $disks
     */
    public static function fromInstances(string $defaultDisk, array $disks): self
    {
        if ($disks === [] || ! isset($disks[$defaultDisk])) {
            throw new InvalidArgumentException('Default storage disk must exist in the disk map.');
        }

        return new self($defaultDisk, null, '', $disks);
    }

    /**
     * @param array<string, mixed> $storageConfig  {@code storage} key from config (whole file array)
     */
    public static function fromConfig(string $basePath, array $storageConfig): self
    {
        $basePath = rtrim($basePath, '/');
        $default = is_string($storageConfig['default'] ?? null)
            ? (string) $storageConfig['default']
            : 'local';

        /** @var array<string, array<string, mixed>> $disksConfig */
        $disksConfig = is_array($storageConfig['disks'] ?? null) ? $storageConfig['disks'] : [];

        if ($disksConfig === []) {
            $disksConfig = [
                'public' => ['driver' => 'local_public'],
                'local' => ['driver' => 'local', 'root' => 'storage/app'],
            ];
        }

        $normalized = [];
        foreach ($disksConfig as $name => $cfg) {
            if (! is_string($name) || ! is_array($cfg)) {
                continue;
            }
            $normalized[$name] = $cfg;
        }

        if ($normalized === []) {
            throw new InvalidArgumentException('No storage disks configured.');
        }

        if (! isset($normalized[$default])) {
            throw new InvalidArgumentException('Default storage disk [' . $default . '] is not defined.');
        }

        return new self($default, $normalized, $basePath, null);
    }

    public static function fromRepository(string $basePath): self
    {
        /** @var array<string, mixed> $config */
        $config = Repository::get('storage', []);

        return self::fromConfig($basePath, $config);
    }

    public function disk(?string $name = null): Filesystem
    {
        $name ??= $this->defaultDisk;

        if ($this->eagerDisks !== null) {
            if (! isset($this->eagerDisks[$name])) {
                throw new InvalidArgumentException('Storage disk [' . $name . '] is not configured.');
            }

            return $this->eagerDisks[$name];
        }

        if ($this->diskConfigs === null || ! isset($this->diskConfigs[$name])) {
            throw new InvalidArgumentException('Storage disk [' . $name . '] is not configured.');
        }

        return $this->lazyResolved[$name] ??= self::makeDriver($this->basePath, $this->diskConfigs[$name]);
    }

    public function defaultDiskName(): string
    {
        return $this->defaultDisk;
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private static function makeDriver(string $basePath, array $cfg): Filesystem
    {
        $driver = isset($cfg['driver']) && is_string($cfg['driver']) ? $cfg['driver'] : 'local';

        return match ($driver) {
            'local' => new LocalFilesystemDriver(self::resolveRoot($basePath, $cfg)),
            'local_public' => new LocalPublicFilesystemDriver(),
            'null' => new NullFilesystemDriver(),
            default => throw new InvalidArgumentException('Unknown storage driver [' . $driver . '].'),
        };
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private static function resolveRoot(string $basePath, array $cfg): string
    {
        $root = isset($cfg['root']) && is_string($cfg['root']) ? trim($cfg['root'], '/') : 'storage/app';
        if ($root === '' || str_contains($root, '..')) {
            throw new InvalidArgumentException('Invalid storage disk root.');
        }

        return $basePath . '/' . $root;
    }
}
