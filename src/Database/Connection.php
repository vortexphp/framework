<?php

declare(strict_types=1);

namespace Vortex\Database;

use PDO;
use PDOStatement;
use Vortex\Config\Repository;

final class Connection
{
    private ?PDO $pdo = null;

    public function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $driver = (string) Repository::get('database.driver', 'sqlite');
        if ($driver === 'sqlite') {
            $database = (string) Repository::get('database.database', ':memory:');
            $this->pdo = new PDO('sqlite:' . $database, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $this->pdo->exec('PRAGMA foreign_keys = ON');

            return $this->pdo;
        }

        $host = (string) Repository::get('database.host', '127.0.0.1');
        $port = (string) Repository::get('database.port', '3306');
        $db = (string) Repository::get('database.database', '');
        $user = (string) Repository::get('database.username', '');
        $pass = (string) Repository::get('database.password', '');

        $dsn = match ($driver) {
            'mysql' => "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
            'pgsql' => "pgsql:host={$host};port={$port};dbname={$db}",
            default => throw new \InvalidArgumentException("Unsupported driver [{$driver}]."),
        };

        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $this->pdo;
    }

    /**
     * @param list<mixed> $bindings
     */
    public function query(string $sql, array $bindings = []): PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);

        return $stmt;
    }

    /**
     * @param list<mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->query($sql, $bindings)->fetchAll();
    }

    /**
     * @param list<mixed> $bindings
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->query($sql, $bindings)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param list<mixed> $bindings
     */
    public function execute(string $sql, array $bindings = []): int
    {
        return $this->query($sql, $bindings)->rowCount();
    }

    public function lastInsertId(): string
    {
        return $this->pdo()->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->pdo()->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo()->commit();
    }

    public function rollBack(): void
    {
        $this->pdo()->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo()->inTransaction();
    }

    /**
     * Runs {@code $callback} inside a transaction. Commits on success; rolls back on any {@see \Throwable} and rethrows.
     *
     * @template T
     * @param callable(self): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($this->inTransaction()) {
                $this->rollBack();
            }
            throw $e;
        }
    }
}
