<?php

declare(strict_types=1);

namespace Vortex\Cache;

use InvalidArgumentException;
use Vortex\Config\Repository;
use Vortex\Contracts\Cache as CacheContract;

/**
 * Named cache stores from {@code config/cache.php}. Drivers are resolved lazily per store.
 */
class CacheManager
{
    /** @var array<string, CacheContract> */
    private array $resolved = [];

    /**
     * @param array<string, array<string, mixed>> $storeConfigs
     */
    private function __construct(
        private readonly string $defaultStore,
        private readonly array $storeConfigs,
        private readonly string $basePath,
        /** @var array<string, CacheContract>|null */
        private readonly ?array $eagerStores = null,
    ) {
    }

    /**
     * @param array<string, CacheContract> $stores
     */
    public static function fromInstances(string $defaultStore, array $stores): self
    {
        if ($stores === [] || ! isset($stores[$defaultStore])) {
            throw new InvalidArgumentException('Default cache store must exist in the store map.');
        }

        return new self($defaultStore, [], '', $stores);
    }

    /**
     * @param array<string, mixed> $cacheConfig  Whole {@code cache.php} return value
     */
    public static function fromConfig(string $basePath, array $cacheConfig): self
    {
        $basePath = rtrim($basePath, '/');

        $storesConfig = isset($cacheConfig['stores']) && is_array($cacheConfig['stores'])
            ? $cacheConfig['stores']
            : null;

        if ($storesConfig === null) {
            return self::fromLegacyConfig($basePath, $cacheConfig);
        }

        $normalized = [];
        foreach ($storesConfig as $name => $cfg) {
            if (is_string($name) && is_array($cfg)) {
                $normalized[$name] = $cfg;
            }
        }

        if ($normalized === []) {
            throw new InvalidArgumentException('No cache stores configured.');
        }

        $default = is_string($cacheConfig['default'] ?? null)
            ? (string) $cacheConfig['default']
            : 'file';

        if (! isset($normalized[$default])) {
            throw new InvalidArgumentException('Default cache store [' . $default . '] is not defined.');
        }

        return new self($default, $normalized, $basePath, null);
    }

    /**
     * @param array<string, mixed> $cacheConfig
     */
    private static function fromLegacyConfig(string $basePath, array $cacheConfig): self
    {
        $driver = strtolower(trim((string) ($cacheConfig['driver'] ?? 'file')));
        if ($driver !== 'file' && $driver !== 'null') {
            throw new InvalidArgumentException("Unsupported cache driver [{$driver}]. Use file or null.");
        }

        $path = isset($cacheConfig['path']) && is_string($cacheConfig['path']) && $cacheConfig['path'] !== ''
            ? $cacheConfig['path']
            : $basePath . '/storage/cache/data';
        $prefix = isset($cacheConfig['prefix']) && is_string($cacheConfig['prefix'])
            ? $cacheConfig['prefix']
            : 'vortex:';

        $stores = [
            'file' => ['driver' => 'file', 'path' => $path, 'prefix' => $prefix],
            'null' => ['driver' => 'null'],
        ];
        $default = $driver === 'null' ? 'null' : 'file';

        return new self($default, $stores, $basePath, null);
    }

    public static function fromRepository(string $basePath): self
    {
        /** @var array<string, mixed> $config */
        $config = Repository::get('cache', []);

        return self::fromConfig($basePath, is_array($config) ? $config : []);
    }

    public function store(?string $name = null): CacheContract
    {
        $name ??= $this->defaultStore;

        if ($this->eagerStores !== null) {
            if (! isset($this->eagerStores[$name])) {
                throw new InvalidArgumentException('Cache store [' . $name . '] is not configured.');
            }

            return $this->eagerStores[$name];
        }

        if (! isset($this->storeConfigs[$name])) {
            throw new InvalidArgumentException('Cache store [' . $name . '] is not configured.');
        }

        return $this->resolved[$name] ??= self::makeDriver($this->basePath, $this->storeConfigs[$name]);
    }

    public function defaultStoreName(): string
    {
        return $this->defaultStore;
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private static function makeDriver(string $basePath, array $cfg): CacheContract
    {
        $driver = isset($cfg['driver']) && is_string($cfg['driver'])
            ? strtolower(trim($cfg['driver']))
            : 'file';

        return match ($driver) {
            'null' => new NullCache(),
            'file' => new FileCache(
                isset($cfg['path']) && is_string($cfg['path']) && $cfg['path'] !== ''
                    ? $cfg['path']
                    : $basePath . '/storage/cache/data',
                isset($cfg['prefix']) && is_string($cfg['prefix']) ? $cfg['prefix'] : 'vortex:',
            ),
            default => throw new InvalidArgumentException('Unknown cache driver [' . $driver . '].'),
        };
    }
}
