<?php

declare(strict_types=1);

namespace Vortex\Database\Schema;

use InvalidArgumentException;
use RuntimeException;
use Vortex\Database\Connection;
use Vortex\Database\DB;

final class Schema
{
    /**
     * @var null|callable(): Connection
     */
    private static $connectionResolver = null;

    private function __construct(
        private readonly Connection $db,
    ) {
    }

    public static function usingConnection(Connection $db): void
    {
        self::$connectionResolver = static fn (): Connection => $db;
    }

    public static function clearConnectionResolver(): void
    {
        self::$connectionResolver = null;
    }

    /**
     * @param callable(Blueprint): void $callback
     */
    public static function create(string $table, callable $callback): void
    {
        (new self(self::resolveConnection()))->createInternal($table, $callback);
    }

    /**
     * @param callable(Blueprint): void $callback
     */
    public static function table(string $table, callable $callback): void
    {
        (new self(self::resolveConnection()))->tableInternal($table, $callback);
    }

    public static function dropIfExists(string $table): void
    {
        (new self(self::resolveConnection()))->dropIfExistsInternal($table);
    }

    public static function hasTable(string $table): bool
    {
        return (new self(self::resolveConnection()))->hasTableInternal($table);
    }

    private static function resolveConnection(): Connection
    {
        if (self::$connectionResolver !== null) {
            $connection = (self::$connectionResolver)();
            if (! $connection instanceof Connection) {
                throw new RuntimeException('Schema connection resolver must return a Connection instance.');
            }

            return $connection;
        }

        return DB::connection();
    }

    /**
     * @param callable(Blueprint): void $callback
     */
    private function createInternal(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, true);
        $callback($blueprint);
        $sql = $this->compileCreateTable($blueprint);
        $this->db->pdo()->exec($sql);
        foreach ($this->compileIndexes($blueprint) as $stmt) {
            $this->db->pdo()->exec($stmt);
        }
    }

    /**
     * @param callable(Blueprint): void $callback
     */
    private function tableInternal(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, false);
        $callback($blueprint);
        foreach ($blueprint->columns() as $column) {
            $sql = 'ALTER TABLE ' . $this->quoteIdent($table) . ' ADD COLUMN ' . $this->compileColumn($column);
            $this->db->pdo()->exec($sql);
        }
        foreach ($this->compileIndexes($blueprint) as $stmt) {
            $this->db->pdo()->exec($stmt);
        }
    }

    private function dropIfExistsInternal(string $table): void
    {
        $this->db->pdo()->exec('DROP TABLE IF EXISTS ' . $this->quoteIdent($table));
    }

    private function hasTableInternal(string $table): bool
    {
        if ($table === '') {
            return false;
        }

        return match ($this->driverName()) {
            'sqlite' => $this->db->selectOne(
                "SELECT 1 AS ok FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1",
                [$table],
            ) !== null,
            'mysql' => $this->db->selectOne(
                'SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
                [$table],
            ) !== null,
            'pgsql' => $this->db->selectOne(
                'SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ? LIMIT 1',
                [$table],
            ) !== null,
            default => throw new RuntimeException('hasTable is not implemented for driver [' . $this->driverName() . '].'),
        };
    }

    private function compileCreateTable(Blueprint $blueprint): string
    {
        $columns = [];
        $foreigns = [];
        foreach ($blueprint->columns() as $column) {
            $columns[] = $this->compileColumn($column);
            if ($column->referencesTable !== null && $column->referencesColumn !== null) {
                $foreign = 'FOREIGN KEY (' . $this->quoteIdent($column->name) . ') REFERENCES '
                    . $this->quoteIdent($column->referencesTable)
                    . '(' . $this->quoteIdent($column->referencesColumn) . ')';
                if ($column->onDelete !== null) {
                    $foreign .= ' ON DELETE ' . $column->onDelete;
                }
                if ($column->onUpdate !== null) {
                    $foreign .= ' ON UPDATE ' . $column->onUpdate;
                }
                $foreigns[] = $foreign;
            }
        }
        if ($columns === []) {
            throw new InvalidArgumentException('Cannot create table without columns.');
        }
        $parts = array_merge($columns, $foreigns);

        return 'CREATE TABLE ' . $this->quoteIdent($blueprint->table()) . ' (' . implode(', ', $parts) . ')';
    }

    /**
     * @return list<string>
     */
    private function compileIndexes(Blueprint $blueprint): array
    {
        $sql = [];
        foreach ($blueprint->indexes() as $index) {
            $name = $index['name'] ?? $this->defaultIndexName($blueprint->table(), $index['columns'], $index['unique']);
            $cols = array_map(fn (string $c): string => $this->quoteIdent($c), $index['columns']);
            $sql[] = ($index['unique'] ? 'CREATE UNIQUE INDEX ' : 'CREATE INDEX ')
                . $this->quoteIdent($name)
                . ' ON '
                . $this->quoteIdent($blueprint->table())
                . ' (' . implode(', ', $cols) . ')';
        }

        return $sql;
    }

    private function compileColumn(ColumnDefinition $column): string
    {
        $driver = $this->driverName();
        $sql = $this->quoteIdent($column->name) . ' ' . $this->columnTypeSql($column, $driver);

        if ($column->unsigned && $driver === 'mysql' && in_array($column->type, ['integer', 'bigInteger', 'smallInteger'], true)) {
            $sql .= ' UNSIGNED';
        }

        if ($column->autoIncrement) {
            if ($driver === 'pgsql' && $column->type === 'id') {
                // BIGSERIAL already includes auto increment semantics.
            } elseif ($driver === 'sqlite' && $column->type === 'id') {
                // INTEGER PRIMARY KEY AUTOINCREMENT already complete.
            } else {
                $sql .= ' AUTO_INCREMENT';
            }
        }

        if ($column->primary && ! ($driver === 'sqlite' && $column->type === 'id')) {
            $sql .= ' PRIMARY KEY';
        }

        if (! $column->nullable && ! ($driver === 'sqlite' && $column->type === 'id')) {
            $sql .= ' NOT NULL';
        }

        if ($column->hasDefault) {
            $sql .= ' DEFAULT ' . $this->literal($column->default);
        }

        return $sql;
    }

    private function columnTypeSql(ColumnDefinition $column, string $driver): string
    {
        if ($column->type === 'decimal') {
            $p = $column->precision ?? 8;
            $s = $column->scale ?? 2;

            return match ($driver) {
                'sqlite', 'mysql' => 'DECIMAL(' . $p . ',' . $s . ')',
                'pgsql' => 'NUMERIC(' . $p . ',' . $s . ')',
                default => throw new RuntimeException('Unsupported driver [' . $driver . '] for schema builder.'),
            };
        }

        return match ($column->type) {
            'id' => match ($driver) {
                'sqlite' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
                'mysql' => 'BIGINT UNSIGNED',
                'pgsql' => 'BIGSERIAL',
                default => throw new RuntimeException('Unsupported driver [' . $driver . '] for schema builder.'),
            },
            'string' => 'VARCHAR(' . (int) ($column->length ?? 255) . ')',
            'char' => 'CHAR(' . (int) ($column->length ?? 1) . ')',
            'text' => 'TEXT',
            'integer' => 'INTEGER',
            'bigInteger' => match ($driver) {
                'sqlite' => 'INTEGER',
                'mysql' => 'BIGINT',
                'pgsql' => 'BIGINT',
                default => throw new RuntimeException('Unsupported driver [' . $driver . '] for schema builder.'),
            },
            'smallInteger' => match ($driver) {
                'sqlite' => 'INTEGER',
                'mysql' => 'SMALLINT',
                'pgsql' => 'SMALLINT',
                default => throw new RuntimeException('Unsupported driver [' . $driver . '] for schema builder.'),
            },
            'float' => match ($driver) {
                'sqlite' => 'REAL',
                'mysql' => 'DOUBLE',
                'pgsql' => 'DOUBLE PRECISION',
                default => throw new RuntimeException('Unsupported driver [' . $driver . '] for schema builder.'),
            },
            'boolean' => $driver === 'sqlite' ? 'INTEGER' : 'BOOLEAN',
            'date' => 'DATE',
            'dateTime' => $driver === 'pgsql' ? 'TIMESTAMP' : 'DATETIME',
            'timestamp' => $driver === 'pgsql' ? 'TIMESTAMP' : 'DATETIME',
            'json' => match ($driver) {
                'sqlite' => 'TEXT',
                'mysql' => 'JSON',
                'pgsql' => 'JSONB',
                default => throw new RuntimeException('Unsupported driver [' . $driver . '] for schema builder.'),
            },
            'foreignId' => match ($driver) {
                'sqlite' => 'INTEGER',
                'mysql' => 'BIGINT UNSIGNED',
                'pgsql' => 'BIGINT',
                default => throw new RuntimeException('Unsupported driver [' . $driver . '] for schema builder.'),
            },
            default => throw new RuntimeException('Unsupported column type [' . $column->type . '].'),
        };
    }

    private function driverName(): string
    {
        $driver = (string) $this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === '') {
            throw new RuntimeException('Could not determine PDO driver.');
        }

        return strtolower($driver);
    }

    private function quoteIdent(string $identifier): string
    {
        if ($identifier === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new InvalidArgumentException('Invalid identifier [' . $identifier . '].');
        }

        return $this->driverName() === 'mysql'
            ? '`' . $identifier . '`'
            : '"' . $identifier . '"';
    }

    private function literal(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            is_string($value) => $this->db->pdo()->quote($value) ?: "''",
            default => throw new InvalidArgumentException('Unsupported default value type.'),
        };
    }

    /**
     * @param list<string> $columns
     */
    private function defaultIndexName(string $table, array $columns, bool $unique): string
    {
        $suffix = $unique ? 'unique' : 'index';

        return strtolower($table . '_' . implode('_', $columns) . '_' . $suffix);
    }
}
