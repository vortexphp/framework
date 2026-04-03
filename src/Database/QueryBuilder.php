<?php

declare(strict_types=1);

namespace Vortex\Database;

/**
 * Single-table SELECT builder (Laravel-like chaining, no external ORM).
 *
 * Column names must come from application code only — never from raw user input.
 */
final class QueryBuilder
{
    /** @var class-string<Model> */
    private string $modelClass;

    /** @var list<array{sql: string, bindings: list<mixed>}> */
    private array $wheres = [];

    private ?string $orderClause = null;

    private ?int $limit = null;

    private ?int $offset = null;

    /**
     * @param class-string<Model> $modelClass
     */
    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
    }

    public function __clone(): void
    {
        $this->wheres = array_values(array_map(
            static fn (array $w): array => ['sql' => $w['sql'], 'bindings' => [...$w['bindings']]],
            $this->wheres,
        ));
    }

    public function where(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $this->wheres[] = ['sql' => "{$column} = ?", 'bindings' => [$operatorOrValue]];
        } else {
            $this->wheres[] = ['sql' => "{$column} {$operatorOrValue} ?", 'bindings' => [$value]];
        }

        return $this;
    }

    /**
     * @param list<mixed> $values
     */
    public function whereIn(string $column, array $values): self
    {
        if ($values === []) {
            $this->wheres[] = ['sql' => '0 = 1', 'bindings' => []];

            return $this;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = ['sql' => "{$column} IN ({$placeholders})", 'bindings' => array_values($values)];

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = ['sql' => "{$column} IS NULL", 'bindings' => []];

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = ['sql' => "{$column} IS NOT NULL", 'bindings' => []];

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderClause = "{$column} {$dir}";

        return $this;
    }

    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, 'DESC');
    }

    public function limit(int $max): self
    {
        $this->limit = max(0, $max);

        return $this;
    }

    public function offset(int $skip): self
    {
        $this->offset = max(0, $skip);

        return $this;
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function compileWhere(): array
    {
        if ($this->wheres === []) {
            return ['', []];
        }

        $parts = [];
        $bindings = [];
        foreach ($this->wheres as $w) {
            $parts[] = $w['sql'];
            foreach ($w['bindings'] as $b) {
                $bindings[] = $b;
            }
        }

        return [' WHERE ' . implode(' AND ', $parts), $bindings];
    }

    public function count(): int
    {
        $modelClass = $this->modelClass;
        [$whereSql, $bindings] = $this->compileWhere();
        $sql = 'SELECT COUNT(*) AS n FROM ' . $modelClass::table() . $whereSql;
        $row = $modelClass::connection()->selectOne($sql, $bindings);

        return (int) ($row['n'] ?? 0);
    }

    public function exists(): bool
    {
        $modelClass = $this->modelClass;
        [$whereSql, $bindings] = $this->compileWhere();
        $sql = 'SELECT 1 AS x FROM ' . $modelClass::table() . $whereSql . ' LIMIT 1';
        $row = $modelClass::connection()->selectOne($sql, $bindings);

        return $row !== null;
    }

    /**
     * @return array{
     *     items: list<Model>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     last_page: int
     * }
     */
    public function paginate(int $page, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $total = $this->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $clone = clone $this;
        $items = $clone->offset($offset)->limit($perPage)->get();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ];
    }

    /**
     * @return list<Model>
     */
    public function get(): array
    {
        $modelClass = $this->modelClass;
        $sql = 'SELECT * FROM ' . $modelClass::table();
        $bindings = [];
        [$whereSql, $whereBindings] = $this->compileWhere();
        $sql .= $whereSql;
        foreach ($whereBindings as $b) {
            $bindings[] = $b;
        }
        if ($this->orderClause !== null) {
            $sql .= ' ORDER BY ' . $this->orderClause;
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        $rows = $modelClass::connection()->select($sql, $bindings);
        $out = [];
        foreach ($rows as $row) {
            $out[] = $modelClass::fromRow($row);
        }

        return $out;
    }

    public function first(): ?Model
    {
        $savedLimit = $this->limit;
        $savedOffset = $this->offset;
        $this->limit = 1;
        $this->offset = null;
        $rows = $this->get();
        $this->limit = $savedLimit;
        $this->offset = $savedOffset;

        return $rows[0] ?? null;
    }
}
