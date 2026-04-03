<?php

declare(strict_types=1);

namespace Vortex\Database;

use PDO;
use PDOStatement;
use Vortex\AppContext;

/**
 * Static access to the default {@see Connection} (same instance as constructor injection and {@see Model::connection()}).
 * Use {@see self::connection()} with a name for non-default connections from {@code config/database.php}.
 */
final class DB
{
    private static function defaultConnection(): Connection
    {
        return AppContext::container()->make(Connection::class);
    }

    public static function connection(?string $name = null): Connection
    {
        if ($name !== null) {
            return AppContext::container()->make(DatabaseManager::class)->connection($name);
        }

        return self::defaultConnection();
    }

    public static function pdo(): PDO
    {
        return self::defaultConnection()->pdo();
    }

    /**
     * @param list<mixed> $bindings
     */
    public static function query(string $sql, array $bindings = []): PDOStatement
    {
        return self::defaultConnection()->query($sql, $bindings);
    }

    /**
     * @param list<mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public static function select(string $sql, array $bindings = []): array
    {
        return self::defaultConnection()->select($sql, $bindings);
    }

    /**
     * @param list<mixed> $bindings
     */
    public static function selectOne(string $sql, array $bindings = []): ?array
    {
        return self::defaultConnection()->selectOne($sql, $bindings);
    }

    /**
     * @param list<mixed> $bindings
     */
    public static function execute(string $sql, array $bindings = []): int
    {
        return self::defaultConnection()->execute($sql, $bindings);
    }

    public static function lastInsertId(): string
    {
        return self::defaultConnection()->lastInsertId();
    }

    public static function beginTransaction(): void
    {
        self::defaultConnection()->beginTransaction();
    }

    public static function commit(): void
    {
        self::defaultConnection()->commit();
    }

    public static function rollBack(): void
    {
        self::defaultConnection()->rollBack();
    }

    public static function inTransaction(): bool
    {
        return self::defaultConnection()->inTransaction();
    }

    /**
     * @template T
     * @param callable(Connection): T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        return self::defaultConnection()->transaction($callback);
    }
}
