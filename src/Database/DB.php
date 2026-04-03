<?php

declare(strict_types=1);

namespace Vortex\Database;

use PDO;
use PDOStatement;
use Vortex\AppContext;

/**
 * Static access to the singleton {@see Connection} (same instance as constructor injection and {@see Model::connection()}).
 */
final class DB
{
    private static function connection(): Connection
    {
        return AppContext::container()->make(Connection::class);
    }

    public static function pdo(): PDO
    {
        return self::connection()->pdo();
    }

    /**
     * @param list<mixed> $bindings
     */
    public static function query(string $sql, array $bindings = []): PDOStatement
    {
        return self::connection()->query($sql, $bindings);
    }

    /**
     * @param list<mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public static function select(string $sql, array $bindings = []): array
    {
        return self::connection()->select($sql, $bindings);
    }

    /**
     * @param list<mixed> $bindings
     */
    public static function selectOne(string $sql, array $bindings = []): ?array
    {
        return self::connection()->selectOne($sql, $bindings);
    }

    /**
     * @param list<mixed> $bindings
     */
    public static function execute(string $sql, array $bindings = []): int
    {
        return self::connection()->execute($sql, $bindings);
    }

    public static function lastInsertId(): string
    {
        return self::connection()->lastInsertId();
    }

    public static function beginTransaction(): void
    {
        self::connection()->beginTransaction();
    }

    public static function commit(): void
    {
        self::connection()->commit();
    }

    public static function rollBack(): void
    {
        self::connection()->rollBack();
    }

    public static function inTransaction(): bool
    {
        return self::connection()->inTransaction();
    }

    /**
     * @template T
     * @param callable(Connection): T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        return self::connection()->transaction($callback);
    }
}
