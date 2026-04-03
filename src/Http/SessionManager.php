<?php

declare(strict_types=1);

namespace Vortex\Http;

use InvalidArgumentException;
use Vortex\Config\Repository;

/**
 * Named session stores from {@code config/session.php}. Drivers are resolved lazily per store.
 */
final class SessionManager
{
    /** @var array<string, SessionStore> */
    private array $resolved = [];

    /**
     * @param array<string, array<string, mixed>> $storeConfigs
     * @param array<string, SessionStore>|null    $eagerStores
     */
    private function __construct(
        private readonly string $defaultStore,
        private readonly array $storeConfigs,
        private readonly ?array $eagerStores = null,
    ) {
    }

    /**
     * @param array<string, SessionStore> $stores
     */
    public static function fromInstances(string $defaultStore, array $stores): self
    {
        if ($stores === [] || ! isset($stores[$defaultStore])) {
            throw new InvalidArgumentException('Default session store must exist in the store map.');
        }

        return new self($defaultStore, [], $stores);
    }

    /**
     * @param array<string, mixed> $sessionConfig
     */
    public static function fromConfig(array $sessionConfig): self
    {
        $storesConfig = isset($sessionConfig['stores']) && is_array($sessionConfig['stores'])
            ? $sessionConfig['stores']
            : null;
        if ($storesConfig === null) {
            throw new InvalidArgumentException('No session stores configured.');
        }

        $normalized = [];
        foreach ($storesConfig as $name => $cfg) {
            if (is_string($name) && is_array($cfg)) {
                $normalized[$name] = $cfg;
            }
        }

        if ($normalized === []) {
            throw new InvalidArgumentException('No session stores configured.');
        }

        $default = is_string($sessionConfig['default'] ?? null)
            ? (string) $sessionConfig['default']
            : 'native';

        if (! isset($normalized[$default])) {
            throw new InvalidArgumentException('Default session store [' . $default . '] is not defined.');
        }

        return new self($default, $normalized, null);
    }

    public static function fromRepository(): self
    {
        /** @var array<string, mixed> $config */
        $config = Repository::get('session', [
            'default' => 'native',
            'stores' => [
                'native' => [
                    'driver' => 'native',
                    'name' => 'pc_session',
                    'lifetime' => 7200,
                    'secure' => false,
                    'samesite' => 'Lax',
                ],
                'null' => [
                    'driver' => 'null',
                ],
            ],
        ]);

        return self::fromConfig(is_array($config) ? $config : []);
    }

    public function store(?string $name = null): SessionStore
    {
        $name ??= $this->defaultStore;

        if ($this->eagerStores !== null) {
            if (! isset($this->eagerStores[$name])) {
                throw new InvalidArgumentException('Session store [' . $name . '] is not configured.');
            }

            return $this->eagerStores[$name];
        }

        if (! isset($this->storeConfigs[$name])) {
            throw new InvalidArgumentException('Session store [' . $name . '] is not configured.');
        }

        return $this->resolved[$name] ??= self::makeDriver($this->storeConfigs[$name]);
    }

    public function defaultStoreName(): string
    {
        return $this->defaultStore;
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private static function makeDriver(array $cfg): SessionStore
    {
        $driver = isset($cfg['driver']) && is_string($cfg['driver'])
            ? strtolower(trim($cfg['driver']))
            : 'native';

        return match ($driver) {
            'null' => new NullSessionStore(),
            'native' => new NativeSessionStore(
                isset($cfg['name']) && is_string($cfg['name']) ? $cfg['name'] : 'vortex_session',
                isset($cfg['lifetime']) ? (int) $cfg['lifetime'] : 0,
                isset($cfg['secure']) ? (bool) $cfg['secure'] : false,
                isset($cfg['samesite']) && is_string($cfg['samesite']) ? $cfg['samesite'] : 'Lax',
            ),
            default => throw new InvalidArgumentException('Unknown session driver [' . $driver . '].'),
        };
    }
}
