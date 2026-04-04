<?php

declare(strict_types=1);

namespace Vortex\Database;

use InvalidArgumentException;
use Vortex\Pagination\Cursor;
use Vortex\Pagination\CursorPaginator;
use Vortex\Pagination\InvalidCursorException;
use Vortex\Pagination\Paginator;

/**
 * Single-table SELECT builder (Laravel-like chaining, no external ORM).
 *
 * Column names must come from application code only — never from raw user input.
 */
final class QueryBuilder
{
    /** @var class-string<Model> */
    private string $modelClass;

    /** @var list<array{scope: ?string, boolean: 'AND'|'OR', sql: string, bindings: list<mixed>}> */
    private array $wheres = [];

    /** @var list<array{type: 'INNER'|'LEFT', table: string, first: string, operator: string, second: string}> */
    private array $joins = [];

    /** @var list<string> */
    private array $groupByColumns = [];

    /** @var list<string> */
    private array $selectColumns = ['*'];

    /** @var list<string> */
    private array $withRelations = [];

    /** @var list<string> */
    private array $orderClauses = [];

    private ?int $limit = null;

    private ?int $offset = null;

    /** @var 'default'|'with'|'only' */
    private string $softDeleteScope = 'default';

    private ?string $globalScopeContext = null;

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
            static fn (array $w): array => [
                'scope' => $w['scope'] ?? null,
                'boolean' => $w['boolean'],
                'sql' => $w['sql'],
                'bindings' => [...$w['bindings']],
            ],
            $this->wheres,
        ));
        $this->joins = array_values(array_map(
            static fn (array $join): array => [
                'type' => $join['type'],
                'table' => $join['table'],
                'first' => $join['first'],
                'operator' => $join['operator'],
                'second' => $join['second'],
            ],
            $this->joins,
        ));
        $this->groupByColumns = [...$this->groupByColumns];
        $this->selectColumns = [...$this->selectColumns];
        $this->withRelations = [...$this->withRelations];
        $this->orderClauses = [...$this->orderClauses];
    }

    public function withTrashed(): self
    {
        if (! $this->modelClass::usesSoftDeletes()) {
            return $this;
        }
        $clone = clone $this;
        $clone->softDeleteScope = 'with';

        return $clone;
    }

    public function onlyTrashed(): self
    {
        if (! $this->modelClass::usesSoftDeletes()) {
            return $this;
        }
        $clone = clone $this;
        $clone->softDeleteScope = 'only';

        return $clone;
    }

    /**
     * @param callable(self): void $callback
     */
    public function applyGlobalScope(string $name, callable $callback): void
    {
        $this->globalScopeContext = $name;
        try {
            $callback($this);
        } finally {
            $this->globalScopeContext = null;
        }
    }

    public function withoutGlobalScope(string $name): self
    {
        $clone = clone $this;
        $clone->wheres = array_values(array_filter(
            $clone->wheres,
            static fn (array $w): bool => ($w['scope'] ?? null) !== $name,
        ));

        return $clone;
    }

    public function withoutGlobalScopes(): self
    {
        $clone = clone $this;
        $clone->wheres = array_values(array_filter(
            $clone->wheres,
            static fn (array $w): bool => ($w['scope'] ?? null) === null,
        ));

        return $clone;
    }

    /**
     * @param string|list<string> $columns
     */
    public function select(string|array $columns): self
    {
        $cols = is_array($columns) ? $columns : [$columns];
        $cols = array_values(array_filter($cols, static fn (string $value): bool => $value !== ''));
        $this->selectColumns = $cols === [] ? ['*'] : $cols;

        return $this;
    }

    /**
     * @param string|list<string> $relations
     */
    public function with(string|array $relations): self
    {
        $rels = is_array($relations) ? $relations : [$relations];
        foreach ($rels as $relation) {
            if ($relation === '' || in_array($relation, $this->withRelations, true)) {
                continue;
            }
            $this->withRelations[] = $relation;
        }

        return $this;
    }

    public function where(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            return $this->addWhere('AND', "{$column} = ?", [$operatorOrValue]);
        }

        return $this->addWhere('AND', "{$column} {$operatorOrValue} ?", [$value]);
    }

    public function orWhere(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            return $this->addWhere('OR', "{$column} = ?", [$operatorOrValue]);
        }

        return $this->addWhere('OR', "{$column} {$operatorOrValue} ?", [$value]);
    }

    /**
     * @param list<mixed> $bindings
     */
    public function whereRaw(string $sql, array $bindings = []): self
    {
        return $this->addWhere('AND', $sql, $bindings);
    }

    /**
     * @param list<mixed> $bindings
     */
    public function orWhereRaw(string $sql, array $bindings = []): self
    {
        return $this->addWhere('OR', $sql, $bindings);
    }

    /**
     * @param callable(self): void $callback
     */
    public function whereGroup(callable $callback): self
    {
        return $this->addGroupedWhere('AND', $callback);
    }

    /**
     * @param callable(self): void $callback
     */
    public function orWhereGroup(callable $callback): self
    {
        return $this->addGroupedWhere('OR', $callback);
    }

    /**
     * @param list<mixed> $values
     */
    public function whereIn(string $column, array $values): self
    {
        if ($values === []) {
            return $this->addWhere('AND', '0 = 1', []);
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        return $this->addWhere('AND', "{$column} IN ({$placeholders})", array_values($values));
    }

    /**
     * @param list<mixed> $values
     */
    public function orWhereIn(string $column, array $values): self
    {
        if ($values === []) {
            return $this->addWhere('OR', '0 = 1', []);
        } else {
            $placeholders = implode(', ', array_fill(0, count($values), '?'));
            return $this->addWhere('OR', "{$column} IN ({$placeholders})", array_values($values));
        }
    }

    public function whereNull(string $column): self
    {
        return $this->addWhere('AND', "{$column} IS NULL", []);
    }

    public function orWhereNull(string $column): self
    {
        return $this->addWhere('OR', "{$column} IS NULL", []);
    }

    public function whereNotNull(string $column): self
    {
        return $this->addWhere('AND', "{$column} IS NOT NULL", []);
    }

    public function join(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => 'INNER',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => 'LEFT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    /**
     * @param string|list<string> $columns
     */
    public function groupBy(string|array $columns): self
    {
        $cols = is_array($columns) ? $columns : [$columns];
        foreach ($cols as $column) {
            if ($column === '') {
                continue;
            }
            $this->groupByColumns[] = $column;
        }

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderClauses[] = "{$column} {$dir}";

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
    private function compileWhere(bool $withKeyword = true): array
    {
        [$softSql, $softBindings] = $this->softDeletePredicate();

        $parts = [];
        $bindings = [];
        foreach ($this->wheres as $index => $w) {
            $prefix = $index === 0 ? '' : (' ' . $w['boolean'] . ' ');
            $parts[] = $prefix . $w['sql'];
            foreach ($w['bindings'] as $b) {
                $bindings[] = $b;
            }
        }

        $inner = implode('', $parts);

        if ($softSql === '' && $inner === '') {
            return ['', []];
        }

        $allBindings = [...$softBindings, ...$bindings];
        if ($softSql !== '' && $inner !== '') {
            $where = '(' . $softSql . ') AND (' . $inner . ')';
        } elseif ($softSql !== '') {
            $where = $softSql;
        } else {
            $where = $inner;
        }

        if ($withKeyword) {
            return [' WHERE ' . $where, $allBindings];
        }

        return [$where, $allBindings];
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function softDeletePredicate(): array
    {
        $col = $this->modelClass::softDeleteColumn();
        if ($col === null) {
            return ['', []];
        }

        return match ($this->softDeleteScope) {
            'with' => ['', []],
            'only' => [$col . ' IS NOT NULL', []],
            default => [$col . ' IS NULL', []],
        };
    }

    private function compileJoin(): string
    {
        if ($this->joins === []) {
            return '';
        }

        $parts = [];
        foreach ($this->joins as $join) {
            $parts[] = $join['type']
                . ' JOIN ' . $join['table']
                . ' ON ' . $join['first'] . ' ' . $join['operator'] . ' ' . $join['second'];
        }

        return ' ' . implode(' ', $parts);
    }

    private function compileGroupBy(): string
    {
        if ($this->groupByColumns === []) {
            return '';
        }

        return ' GROUP BY ' . implode(', ', $this->groupByColumns);
    }

    private function compileOrderBy(): string
    {
        if ($this->orderClauses === []) {
            return '';
        }

        return ' ORDER BY ' . implode(', ', $this->orderClauses);
    }

    private function compileLimitOffset(): string
    {
        $sql = '';
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    /**
     * @param list<mixed> $bindings
     */
    private function addWhere(string $boolean, string $sql, array $bindings): self
    {
        $this->wheres[] = [
            'scope' => $this->globalScopeContext,
            'boolean' => $boolean === 'OR' ? 'OR' : 'AND',
            'sql' => $sql,
            'bindings' => $bindings,
        ];

        return $this;
    }

    /**
     * @param callable(self): void $callback
     */
    private function addGroupedWhere(string $boolean, callable $callback): self
    {
        $nested = new self($this->modelClass);
        $nested->softDeleteScope = $this->softDeleteScope;
        $callback($nested);
        [$nestedSql, $nestedBindings] = $nested->compileWhere(false);
        if ($nestedSql === '') {
            return $this;
        }

        return $this->addWhere($boolean, '(' . $nestedSql . ')', $nestedBindings);
    }

    public function count(): int
    {
        $modelClass = $this->modelClass;
        [$whereSql, $bindings] = $this->compileWhere();
        $sql = 'SELECT COUNT(*) AS n FROM ' . $modelClass::table()
            . $this->compileJoin()
            . $whereSql;
        $row = $modelClass::connection()->selectOne($sql, $bindings);

        return (int) ($row['n'] ?? 0);
    }

    public function exists(): bool
    {
        $modelClass = $this->modelClass;
        [$whereSql, $bindings] = $this->compileWhere();
        $sql = 'SELECT 1 AS x FROM ' . $modelClass::table()
            . $this->compileJoin()
            . $whereSql
            . ' LIMIT 1';
        $row = $modelClass::connection()->selectOne($sql, $bindings);

        return $row !== null;
    }

    /**
     * @return list<mixed>
     */
    public function pluck(string $column): array
    {
        $modelClass = $this->modelClass;
        [$whereSql, $bindings] = $this->compileWhere();
        $sql = 'SELECT ' . $column . ' FROM ' . $modelClass::table()
            . $this->compileJoin()
            . $whereSql
            . $this->compileGroupBy()
            . $this->compileOrderBy()
            . $this->compileLimitOffset();

        $rows = $modelClass::connection()->select($sql, $bindings);
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row[$column] ?? null;
        }

        return $out;
    }

    public function value(string $column): mixed
    {
        $savedLimit = $this->limit;
        $savedOffset = $this->offset;
        $this->limit = 1;
        $this->offset = null;
        $values = $this->pluck($column);
        $this->limit = $savedLimit;
        $this->offset = $savedOffset;

        return $values[0] ?? null;
    }

    /**
     * @return Paginator rows in {@see Paginator::$items} are instances of this builder’s model
     */
    public function paginate(int $page, int $perPage = 15): Paginator
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $total = $this->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $clone = clone $this;
        /** @var list<Model> $items */
        $items = $clone->offset($offset)->limit($perPage)->get();

        return new Paginator($items, $total, $page, $perPage, $lastPage);
    }

    /**
     * Cursor-based page using a single sort column (stable {@code ORDER BY} on that column).
     * Fetches {@code perPage + 1} rows to set {@see CursorPaginator::$has_more} and {@see CursorPaginator::$next_cursor}.
     *
     * {@see Cursor::decode()} must yield a JSON object that includes {@code $column} (e.g. {@code {"id": 7}}).
     *
     * @return CursorPaginator rows in {@see CursorPaginator::$items} are instances of this builder’s model
     *
     * @throws InvalidCursorException When {@code $cursor} is non-empty but invalid or missing {@code $column}
     */
    public function cursorPaginate(
        ?string $cursor = null,
        int $perPage = 15,
        string $column = 'id',
        string $direction = 'ASC',
    ): CursorPaginator {
        $perPage = max(1, min(100, $perPage));
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $bound = null;
        if ($cursor !== null && $cursor !== '') {
            $payload = Cursor::decode($cursor);
            if (! array_key_exists($column, $payload)) {
                throw new InvalidCursorException(sprintf('Cursor is missing "%s"', $column));
            }
            $bound = $payload[$column];
        }

        $clone = clone $this;
        if ($bound !== null) {
            $op = $dir === 'ASC' ? '>' : '<';
            $clone->where($column, $op, $bound);
        }
        $clone->orderBy($column, $dir);
        $clone->limit($perPage + 1);
        /** @var list<Model> $rows */
        $rows = $clone->get();
        $hasMore = count($rows) > $perPage;
        if ($hasMore) {
            array_pop($rows);
        }
        $next = null;
        if ($hasMore && $rows !== []) {
            $last = $rows[array_key_last($rows)];
            $lastVal = self::cursorValueFromModel($last, $column);
            $next = Cursor::encode([$column => $lastVal]);
        }

        return new CursorPaginator($rows, $next, $hasMore, $perPage);
    }

    private static function cursorValueFromModel(Model $model, string $column): mixed
    {
        $key = str_contains($column, '.') ? substr($column, (int) strrpos($column, '.') + 1) : $column;

        return $model->{$key};
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRaw(): array
    {
        $modelClass = $this->modelClass;
        $sql = 'SELECT ' . implode(', ', $this->selectColumns) . ' FROM ' . $modelClass::table()
            . $this->compileJoin();
        [$whereSql, $bindings] = $this->compileWhere();
        $sql .= $whereSql
            . $this->compileGroupBy()
            . $this->compileOrderBy()
            . $this->compileLimitOffset();

        return $modelClass::connection()->select($sql, $bindings);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function firstRaw(): ?array
    {
        $savedLimit = $this->limit;
        $savedOffset = $this->offset;
        $this->limit = 1;
        $this->offset = null;
        $rows = $this->getRaw();
        $this->limit = $savedLimit;
        $this->offset = $savedOffset;

        return $rows[0] ?? null;
    }

    /**
     * @return list<Model>
     */
    public function get(): array
    {
        $modelClass = $this->modelClass;
        $rows = $this->getRaw();
        $out = [];
        foreach ($rows as $row) {
            $out[] = $modelClass::fromRow($row);
        }
        $this->eagerLoad($out);

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

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(array $attributes): int
    {
        if ($attributes === []) {
            return 0;
        }

        $modelClass = $this->modelClass;
        $columns = array_keys($attributes);
        $setSql = implode(', ', array_map(static fn (string $column): string => $column . ' = ?', $columns));
        [$whereSql, $whereBindings] = $this->compileWhere();
        $sql = 'UPDATE ' . $modelClass::table()
            . ' SET ' . $setSql
            . $whereSql;
        $bindings = array_values($attributes);
        foreach ($whereBindings as $binding) {
            $bindings[] = $binding;
        }

        return $modelClass::connection()->execute($sql, $bindings);
    }

    public function delete(): int
    {
        $modelClass = $this->modelClass;
        $col = $modelClass::softDeleteColumn();
        if ($col !== null && $this->softDeleteScope !== 'only') {
            $now = date('Y-m-d H:i:s');
            $sets = [$col => $now];
            if ($modelClass::usesTimestamps()) {
                $sets['updated_at'] = $now;
            }

            return $this->update($sets);
        }

        [$whereSql, $bindings] = $this->compileWhere();
        $sql = 'DELETE FROM ' . $modelClass::table() . $whereSql;

        return $modelClass::connection()->execute($sql, $bindings);
    }

    /**
     * @param list<Model> $models
     */
    private function eagerLoad(array $models): void
    {
        if ($models === [] || $this->withRelations === []) {
            return;
        }

        $this->eagerLoadPathsForModels($models, $this->modelClass, $this->withRelations);
    }

    /**
     * Run {@see with()} paths against models already in memory (batch queries; same engine as {@see get()}).
     *
     * @param list<Model> $models
     */
    public function eagerLoadOnto(array $models): void
    {
        $this->eagerLoad($models);
    }

    /**
     * @param list<string> $paths Dot-separated relation paths (e.g. {@code author.country}).
     *
     * @return array<string, list<string>> First segment => remainder paths to eager-load on the related model.
     */
    private function groupEagerPaths(array $paths): array
    {
        $grouped = [];
        foreach ($paths as $raw) {
            if (! is_string($raw)) {
                continue;
            }
            $raw = trim($raw);
            if ($raw === '') {
                continue;
            }
            $dot = strpos($raw, '.');
            if ($dot === false) {
                $first = $raw;
                $suffix = null;
            } else {
                $first = substr($raw, 0, $dot);
                $suffix = substr($raw, $dot + 1);
                if ($first === '') {
                    continue;
                }
            }
            $grouped[$first] ??= [];
            if ($suffix !== null && $suffix !== '') {
                $grouped[$first][] = $suffix;
            }
        }

        return $grouped;
    }

    /**
     * @param list<Model> $models
     * @param class-string<Model> $modelClass
     * @param list<string> $paths
     */
    private function eagerLoadPathsForModels(array $models, string $modelClass, array $paths): void
    {
        if ($models === [] || $paths === []) {
            return;
        }

        foreach ($this->groupEagerPaths($paths) as $relation => $nestedSuffixes) {
            $nestedSuffixes = array_values(array_unique($nestedSuffixes));
            $this->eagerLoadRelationBranch($models, $modelClass, $relation, $nestedSuffixes);
        }
    }

    /**
     * @param list<Model> $models
     * @param class-string<Model> $modelClass
     * @param list<string> $nestedSuffixes Remainders after {@code $relation.} (may be empty).
     */
    private function eagerLoadRelationBranch(
        array $models,
        string $modelClass,
        string $relation,
        array $nestedSuffixes,
    ): void {
        $spec = $modelClass::eagerRelationSpec($relation);
        if ($spec !== null) {
            $this->eagerLoadWithSpec($models, $relation, $spec, $modelClass);
        } else {
            foreach ($models as $model) {
                if (! method_exists($model, $relation)) {
                    continue;
                }
                $model->{$relation} = $model->{$relation}();
            }
        }

        if ($nestedSuffixes === []) {
            return;
        }

        $relatedClass = $this->relatedClassFromEagerSpec($spec);
        $children = $this->collectNestedRelatedModels($models, $relation);
        if ($children === []) {
            return;
        }
        if ($relatedClass !== null) {
            $this->eagerLoadPathsForModels($children, $relatedClass, $nestedSuffixes);

            return;
        }
        foreach ($this->groupModelsByConcreteClass($children) as $class => $group) {
            $this->eagerLoadPathsForModels($group, $class, $nestedSuffixes);
        }
    }

    /**
     * @param list<mixed>|null $spec
     *
     * @return class-string<Model>|null
     */
    private function relatedClassFromEagerSpec(?array $spec): ?string
    {
        if ($spec === null || ! isset($spec[1]) || ! is_string($spec[1])) {
            return null;
        }
        if (! is_subclass_of($spec[1], Model::class)) {
            return null;
        }

        /** @var class-string<Model> */
        return $spec[1];
    }

    /**
     * @param list<Model> $models
     *
     * @return array<class-string<Model>, list<Model>>
     */
    private function groupModelsByConcreteClass(array $models): array
    {
        $out = [];
        foreach ($models as $m) {
            $class = $m::class;
            $out[$class][] = $m;
        }

        return $out;
    }

    /**
     * @param list<Model> $models
     *
     * @return list<Model>
     */
    private function collectNestedRelatedModels(array $models, string $relation): array
    {
        $out = [];
        $seen = [];
        foreach ($models as $m) {
            $rel = $m->{$relation} ?? null;
            if ($rel === null) {
                continue;
            }
            if (is_array($rel)) {
                foreach ($rel as $item) {
                    if ($item instanceof Model) {
                        $oid = spl_object_id($item);
                        if (! isset($seen[$oid])) {
                            $seen[$oid] = true;
                            $out[] = $item;
                        }
                    }
                }
            } elseif ($rel instanceof Model) {
                $oid = spl_object_id($rel);
                if (! isset($seen[$oid])) {
                    $seen[$oid] = true;
                    $out[] = $rel;
                }
            }
        }

        return $out;
    }

    /**
     * @param list<Model> $models
     * @param list<mixed> $spec
     * @param class-string<Model> $onClass
     */
    private function eagerLoadWithSpec(array $models, string $relation, array $spec, string $onClass): void
    {
        $type = $spec[0] ?? null;
        match ($type) {
            'belongsTo' => $this->eagerLoadBelongsTo($models, $relation, $spec),
            'hasMany' => $this->eagerLoadHasMany($models, $relation, $spec),
            'hasOne' => $this->eagerLoadHasOne($models, $relation, $spec),
            'belongsToMany' => $this->eagerLoadBelongsToMany($models, $relation, $spec),
            'morphTo' => $this->eagerLoadMorphTo($models, $relation, $spec),
            'morphMany' => $this->eagerLoadMorphMany($models, $relation, $spec),
            'morphOne' => $this->eagerLoadMorphOne($models, $relation, $spec),
            default => throw new InvalidArgumentException(
                "Invalid eager relation type for \"{$relation}\" on {$onClass}",
            ),
        };
    }

    /**
     * @param list<Model> $models
     * @param list<mixed> $spec
     */
    private function eagerLoadBelongsTo(array $models, string $relation, array $spec): void
    {
        if (! isset($spec[1], $spec[2]) || ! is_string($spec[1]) || ! is_string($spec[2])) {
            throw new InvalidArgumentException("Invalid belongsTo spec for \"{$relation}\"");
        }
        /** @var class-string<Model> $relatedClass */
        $relatedClass = $spec[1];
        $foreignKey = $spec[2];
        $ownerKey = isset($spec[3]) && is_string($spec[3]) && $spec[3] !== '' ? $spec[3] : 'id';

        $ids = [];
        foreach ($models as $m) {
            $v = $m->{$foreignKey} ?? null;
            if ($v !== null && $v !== '') {
                $ids[] = $v;
            }
        }
        $ids = array_values(array_unique($ids, SORT_REGULAR));
        if ($ids === []) {
            foreach ($models as $m) {
                $m->{$relation} = null;
            }

            return;
        }

        /** @var list<Model> $related */
        $related = $relatedClass::query()->whereIn($ownerKey, $ids)->get();
        $byOwner = [];
        foreach ($related as $rm) {
            $byOwner[(string) ($rm->{$ownerKey} ?? '')] = $rm;
        }
        foreach ($models as $m) {
            $fk = $m->{$foreignKey} ?? null;
            $m->{$relation} = ($fk !== null && $fk !== '')
                ? ($byOwner[(string) $fk] ?? null)
                : null;
        }
    }

    /**
     * @param list<Model> $models
     * @param list<mixed> $spec
     */
    private function eagerLoadHasMany(array $models, string $relation, array $spec): void
    {
        if (! isset($spec[1], $spec[2]) || ! is_string($spec[1]) || ! is_string($spec[2])) {
            throw new InvalidArgumentException("Invalid hasMany spec for \"{$relation}\"");
        }
        /** @var class-string<Model> $relatedClass */
        $relatedClass = $spec[1];
        $foreignKey = $spec[2];
        $localKey = isset($spec[3]) && is_string($spec[3]) && $spec[3] !== '' ? $spec[3] : 'id';

        $parentIds = [];
        foreach ($models as $m) {
            $v = $m->{$localKey} ?? null;
            if ($v !== null && $v !== '') {
                $parentIds[] = $v;
            }
        }
        $parentIds = array_values(array_unique($parentIds, SORT_REGULAR));
        if ($parentIds === []) {
            foreach ($models as $m) {
                $m->{$relation} = [];
            }

            return;
        }

        /** @var list<Model> $children */
        $children = $relatedClass::query()->whereIn($foreignKey, $parentIds)->orderBy('id')->get();
        $grouped = [];
        foreach ($children as $c) {
            $pk = (string) ($c->{$foreignKey} ?? '');
            $grouped[$pk][] = $c;
        }
        foreach ($models as $m) {
            $id = (string) ($m->{$localKey} ?? '');
            $m->{$relation} = $grouped[$id] ?? [];
        }
    }

    /**
     * @param list<Model> $models
     * @param list<mixed> $spec
     */
    private function eagerLoadHasOne(array $models, string $relation, array $spec): void
    {
        if (! isset($spec[1], $spec[2]) || ! is_string($spec[1]) || ! is_string($spec[2])) {
            throw new InvalidArgumentException("Invalid hasOne spec for \"{$relation}\"");
        }
        /** @var class-string<Model> $relatedClass */
        $relatedClass = $spec[1];
        $foreignKey = $spec[2];
        $localKey = isset($spec[3]) && is_string($spec[3]) && $spec[3] !== '' ? $spec[3] : 'id';

        $parentIds = [];
        foreach ($models as $m) {
            $v = $m->{$localKey} ?? null;
            if ($v !== null && $v !== '') {
                $parentIds[] = $v;
            }
        }
        $parentIds = array_values(array_unique($parentIds, SORT_REGULAR));
        if ($parentIds === []) {
            foreach ($models as $m) {
                $m->{$relation} = null;
            }

            return;
        }

        /** @var list<Model> $children */
        $children = $relatedClass::query()->whereIn($foreignKey, $parentIds)->orderBy('id')->get();
        $grouped = [];
        foreach ($children as $c) {
            $pk = (string) ($c->{$foreignKey} ?? '');
            if ($pk === '' || isset($grouped[$pk])) {
                continue;
            }
            $grouped[$pk] = $c;
        }
        foreach ($models as $m) {
            $id = (string) ($m->{$localKey} ?? '');
            $m->{$relation} = $grouped[$id] ?? null;
        }
    }

    /**
     * @param list<Model> $models
     * @param list<mixed> $spec
     */
    private function eagerLoadBelongsToMany(array $models, string $relation, array $spec): void
    {
        if (! isset($spec[1], $spec[2], $spec[3], $spec[4])
            || ! is_string($spec[1])
            || ! is_string($spec[2])
            || ! is_string($spec[3])
            || ! is_string($spec[4])) {
            throw new InvalidArgumentException("Invalid belongsToMany spec for \"{$relation}\"");
        }
        /** @var class-string<Model> $relatedClass */
        $relatedClass = $spec[1];
        $pivot = $spec[2];
        $foreignPivotKey = $spec[3];
        $relatedPivotKey = $spec[4];
        $parentKey = isset($spec[5]) && is_string($spec[5]) && $spec[5] !== '' ? $spec[5] : 'id';
        $relatedKey = isset($spec[6]) && is_string($spec[6]) && $spec[6] !== '' ? $spec[6] : 'id';

        $parentIds = [];
        foreach ($models as $m) {
            $v = $m->{$parentKey} ?? null;
            if ($v !== null && $v !== '') {
                $parentIds[] = $v;
            }
        }
        $parentIds = array_values(array_unique($parentIds, SORT_REGULAR));
        if ($parentIds === []) {
            foreach ($models as $m) {
                $m->{$relation} = [];
            }

            return;
        }

        $table = $relatedClass::table();
        $placeholders = implode(', ', array_fill(0, count($parentIds), '?'));
        $alias = '__eager_parent';
        $sql = 'SELECT r.*, p.' . $foreignPivotKey . ' AS ' . $alias
            . ' FROM ' . $table . ' r'
            . ' INNER JOIN ' . $pivot . ' p ON p.' . $relatedPivotKey . ' = r.' . $relatedKey
            . ' WHERE p.' . $foreignPivotKey . ' IN (' . $placeholders . ')'
            . ' ORDER BY p.' . $foreignPivotKey . ', p.' . $relatedPivotKey;
        $rows = $relatedClass::connection()->select($sql, $parentIds);

        $grouped = [];
        foreach ($rows as $row) {
            $pid = (string) ($row[$alias] ?? '');
            unset($row[$alias]);
            $grouped[$pid][] = $relatedClass::fromRow($row);
        }
        foreach ($models as $m) {
            $id = (string) ($m->{$parentKey} ?? '');
            $m->{$relation} = $grouped[$id] ?? [];
        }
    }

    /**
     * @param list<Model> $models
     * @param list<mixed> $spec
     */
    private function eagerLoadMorphTo(array $models, string $relation, array $spec): void
    {
        if (! isset($spec[1]) || ! is_string($spec[1]) || $spec[1] === '') {
            throw new InvalidArgumentException("Invalid morphTo spec for \"{$relation}\"");
        }
        $name = $spec[1];
        $typeKey = $name . '_type';
        $idKey = $name . '_id';

        /** @var array<class-string<Model>, list<mixed>> $idsByClass */
        $idsByClass = [];
        foreach ($models as $m) {
            $stored = $m->{$typeKey} ?? null;
            $rid = $m->{$idKey} ?? null;
            if (! is_string($stored) || $stored === '' || $rid === null || $rid === '') {
                continue;
            }
            $resolved = MorphMap::resolveClass($stored);
            if ($resolved === null) {
                continue;
            }
            $idsByClass[$resolved][(string) $rid] = $rid;
        }
        foreach ($idsByClass as $cls => $ids) {
            $idsByClass[$cls] = array_values(array_unique(array_values($ids), SORT_REGULAR));
        }

        /** @var array<class-string<Model>, array<string, Model>> $rowsByClass */
        $rowsByClass = [];
        foreach ($idsByClass as $cls => $ids) {
            if ($ids === []) {
                continue;
            }
            /** @var list<Model> $rows */
            $rows = $cls::query()->whereIn('id', $ids)->get();
            foreach ($rows as $rm) {
                $pk = $rm->id ?? null;
                if ($pk !== null && $pk !== '') {
                    $rowsByClass[$cls][(string) $pk] = $rm;
                }
            }
        }

        foreach ($models as $m) {
            $stored = $m->{$typeKey} ?? null;
            $rid = $m->{$idKey} ?? null;
            if (! is_string($stored) || $stored === '' || $rid === null || $rid === '') {
                $m->{$relation} = null;

                continue;
            }
            $resolved = MorphMap::resolveClass($stored);
            if ($resolved === null) {
                $m->{$relation} = null;

                continue;
            }
            $m->{$relation} = $rowsByClass[$resolved][(string) $rid] ?? null;
        }
    }

    /**
     * @param list<Model> $models
     * @param list<mixed> $spec
     */
    private function eagerLoadMorphMany(array $models, string $relation, array $spec): void
    {
        if (! isset($spec[1], $spec[2]) || ! is_string($spec[1]) || ! is_string($spec[2])) {
            throw new InvalidArgumentException("Invalid morphMany spec for \"{$relation}\"");
        }
        /** @var class-string<Model> $relatedClass */
        $relatedClass = $spec[1];
        $morphName = $spec[2];
        $localKey = isset($spec[3]) && is_string($spec[3]) && $spec[3] !== '' ? $spec[3] : 'id';
        $typeCol = $morphName . '_type';
        $idCol = $morphName . '_id';

        $parentMorph = $models[0]::getMorphClass();

        $parentIds = [];
        foreach ($models as $m) {
            $v = $m->{$localKey} ?? null;
            if ($v !== null && $v !== '') {
                $parentIds[] = $v;
            }
        }
        $parentIds = array_values(array_unique($parentIds, SORT_REGULAR));
        if ($parentIds === []) {
            foreach ($models as $m) {
                $m->{$relation} = [];
            }

            return;
        }

        /** @var list<Model> $children */
        $children = $relatedClass::query()
            ->where($typeCol, $parentMorph)
            ->whereIn($idCol, $parentIds)
            ->orderBy('id')
            ->get();
        $grouped = [];
        foreach ($children as $c) {
            $pk = (string) ($c->{$idCol} ?? '');
            $grouped[$pk][] = $c;
        }
        foreach ($models as $m) {
            $id = (string) ($m->{$localKey} ?? '');
            $m->{$relation} = $grouped[$id] ?? [];
        }
    }

    /**
     * @param list<Model> $models
     * @param list<mixed> $spec
     */
    private function eagerLoadMorphOne(array $models, string $relation, array $spec): void
    {
        if (! isset($spec[1], $spec[2]) || ! is_string($spec[1]) || ! is_string($spec[2])) {
            throw new InvalidArgumentException("Invalid morphOne spec for \"{$relation}\"");
        }
        /** @var class-string<Model> $relatedClass */
        $relatedClass = $spec[1];
        $morphName = $spec[2];
        $localKey = isset($spec[3]) && is_string($spec[3]) && $spec[3] !== '' ? $spec[3] : 'id';
        $typeCol = $morphName . '_type';
        $idCol = $morphName . '_id';

        $parentMorph = $models[0]::getMorphClass();

        $parentIds = [];
        foreach ($models as $m) {
            $v = $m->{$localKey} ?? null;
            if ($v !== null && $v !== '') {
                $parentIds[] = $v;
            }
        }
        $parentIds = array_values(array_unique($parentIds, SORT_REGULAR));
        if ($parentIds === []) {
            foreach ($models as $m) {
                $m->{$relation} = null;
            }

            return;
        }

        /** @var list<Model> $children */
        $children = $relatedClass::query()
            ->where($typeCol, $parentMorph)
            ->whereIn($idCol, $parentIds)
            ->orderBy('id')
            ->get();
        $grouped = [];
        foreach ($children as $c) {
            $pk = (string) ($c->{$idCol} ?? '');
            if ($pk === '' || isset($grouped[$pk])) {
                continue;
            }
            $grouped[$pk] = $c;
        }
        foreach ($models as $m) {
            $id = (string) ($m->{$localKey} ?? '');
            $m->{$relation} = $grouped[$id] ?? null;
        }
    }
}
