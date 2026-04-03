<?php

declare(strict_types=1);

namespace Vortex\Database;

use InvalidArgumentException;
use PDO;
use Vortex\Config\Repository;

/**
 * Named PDO connections from {@code config/database.php}. Drivers are resolved lazily per connection.
 */
class DatabaseManager
{
    /** @var array<string, Connection> */
    private array $resolved = [];

    /**
     * @param array<string, array<string, mixed>> $connectionConfigs
     * @param array<string, Connection>|null     $eagerConnections
     */
    private function __construct(
        private readonly string $defaultConnection,
        private readonly array $connectionConfigs,
        private readonly ?array $eagerConnections = null,
    ) {
    }

    /**
     * @param array<string, Connection> $connections
     */
    public static function fromInstances(string $defaultConnection, array $connections): self
    {
        if ($connections === [] || ! isset($connections[$defaultConnection])) {
            throw new InvalidArgumentException('Default database connection must exist in the connection map.');
        }

        return new self($defaultConnection, [], $connections);
    }

    /**
     * @param array<string, mixed> $databaseConfig  Whole {@code database.php} return value
     */
    public static function fromConfig(array $databaseConfig): self
    {
        $raw = isset($databaseConfig['connections']) && is_array($databaseConfig['connections'])
            ? $databaseConfig['connections']
            : null;
        if ($raw === null) {
            throw new InvalidArgumentException('No database connections configured.');
        }

        $normalized = [];
        foreach ($raw as $name => $cfg) {
            if (is_string($name) && is_array($cfg)) {
                $normalized[$name] = $cfg;
            }
        }

        if ($normalized === []) {
            throw new InvalidArgumentException('No database connections configured.');
        }

        $default = is_string($databaseConfig['default'] ?? null)
            ? (string) $databaseConfig['default']
            : 'default';

        if (! isset($normalized[$default])) {
            throw new InvalidArgumentException('Default database connection [' . $default . '] is not defined.');
        }

        return new self($default, $normalized, null);
    }

    public static function fromRepository(): self
    {
        /** @var array<string, mixed> $config */
        $config = Repository::get('database', []);

        return self::fromConfig(is_array($config) ? $config : []);
    }

    public function connection(?string $name = null): Connection
    {
        $name ??= $this->defaultConnection;

        if ($this->eagerConnections !== null) {
            if (! isset($this->eagerConnections[$name])) {
                throw new InvalidArgumentException('Database connection [' . $name . '] is not configured.');
            }

            return $this->eagerConnections[$name];
        }

        if (! isset($this->connectionConfigs[$name])) {
            throw new InvalidArgumentException('Database connection [' . $name . '] is not configured.');
        }

        return $this->resolved[$name] ??= new Connection(self::makePdo($this->connectionConfigs[$name]));
    }

    public function defaultConnectionName(): string
    {
        return $this->defaultConnection;
    }

    /**
     * @param array<string, mixed> $cfg
     */
    public static function makePdo(array $cfg): PDO
    {
        $driver = isset($cfg['driver']) && is_string($cfg['driver'])
            ? strtolower(trim($cfg['driver']))
            : 'sqlite';

        if ($driver === 'sqlite') {
            $database = isset($cfg['database']) && is_string($cfg['database']) && $cfg['database'] !== ''
                ? $cfg['database']
                : ':memory:';
            $pdo = new PDO('sqlite:' . $database, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');

            return $pdo;
        }

        $host = isset($cfg['host']) && is_string($cfg['host']) ? $cfg['host'] : '127.0.0.1';
        $port = isset($cfg['port']) && is_string($cfg['port']) ? $cfg['port'] : '3306';
        $db = isset($cfg['database']) && is_string($cfg['database']) ? $cfg['database'] : '';
        $user = isset($cfg['username']) && is_string($cfg['username']) ? $cfg['username'] : '';
        $pass = isset($cfg['password']) && is_string($cfg['password']) ? $cfg['password'] : '';

        $dsn = match ($driver) {
            'mysql' => "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
            'pgsql' => "pgsql:host={$host};port={$port};dbname={$db}",
            default => throw new InvalidArgumentException('Unknown database driver [' . $driver . '].'),
        };

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}
