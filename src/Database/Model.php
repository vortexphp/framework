<?php

declare(strict_types=1);

namespace Vortex\Database;

use LogicException;
use Vortex\AppContext;

#[\AllowDynamicProperties]
abstract class Model
{
    protected static ?string $table = null;

    /** @var list<string> */
    protected static array $fillable = [];

    protected static bool $timestamps = true;

    public static function connection(): Connection
    {
        return AppContext::container()->make(Connection::class);
    }

    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::class);
    }

    public static function table(): string
    {
        if (static::$table !== null && static::$table !== '') {
            return static::$table;
        }

        $class = static::class;
        $pos = strrpos($class, '\\');
        $base = $pos === false ? $class : substr($class, $pos + 1);
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $base) ?? $base);

        return self::pluralizeSnake($snake);
    }

    private static function pluralizeSnake(string $snake): string
    {
        if ($snake !== '' && preg_match('/[^aeiou]y$/', $snake) === 1) {
            return substr($snake, 0, -1) . 'ies';
        }

        if (preg_match('/(s|x|z|ch|sh)$/', $snake) === 1) {
            return $snake . 'es';
        }

        return $snake . 's';
    }

    /**
     * @return list<static>
     */
    public static function all(): array
    {
        $rows = static::connection()->select('SELECT * FROM ' . static::table());
        $out = [];
        foreach ($rows as $row) {
            $out[] = static::fromRow($row);
        }

        return $out;
    }

    public static function find(mixed $id): ?static
    {
        $row = static::connection()->selectOne(
            'SELECT * FROM ' . static::table() . ' WHERE id = ? LIMIT 1',
            [$id],
        );

        return $row === null ? null : static::fromRow($row);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function create(array $attributes): static
    {
        $filtered = static::onlyFillable($attributes);
        if (static::$timestamps) {
            $now = date('Y-m-d H:i:s');
            $filtered['created_at'] ??= $now;
            $filtered['updated_at'] ??= $now;
        }

        $cols = array_keys($filtered);
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $colList = implode(', ', $cols);
        $sql = 'INSERT INTO ' . static::table() . " ({$colList}) VALUES ({$placeholders})";
        static::connection()->execute($sql, array_values($filtered));
        $id = static::connection()->lastInsertId();

        return static::find($id) ?? static::fromRow($filtered + ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): static
    {
        $m = new static();
        foreach ($row as $k => $v) {
            $m->{$k} = $v;
        }

        return $m;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected static function onlyFillable(array $attributes): array
    {
        if (static::$fillable === []) {
            return $attributes;
        }

        return array_intersect_key($attributes, array_flip(static::$fillable));
    }

    /**
     * Fillable keys that must never appear in an UPDATE (e.g. ownership set only at create).
     *
     * @return list<string>
     */
    protected static function excludedFromUpdate(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $attributes fillable only
     */
    public static function updateRecord(int $id, array $attributes): void
    {
        $filtered = static::onlyFillable($attributes);
        foreach (static::excludedFromUpdate() as $key) {
            unset($filtered[$key]);
        }

        if ($filtered === []) {
            return;
        }

        if (static::$timestamps) {
            $filtered['updated_at'] = date('Y-m-d H:i:s');
        }

        $cols = array_keys($filtered);
        $set = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', $cols));
        $bindings = array_values($filtered);
        $bindings[] = $id;

        static::connection()->execute(
            'UPDATE ' . static::table() . ' SET ' . $set . ' WHERE id = ?',
            $bindings,
        );
    }

    public static function deleteId(int $id): void
    {
        static::connection()->execute('DELETE FROM ' . static::table() . ' WHERE id = ?', [$id]);
    }

    /**
     * Resolve an inverse one-to-many relation.
     *
     * @template TRelated of Model
     * @param class-string<TRelated> $relatedClass
     * @return TRelated|null
     */
    protected function belongsTo(string $relatedClass, string $foreignKey, string $ownerKey = 'id'): ?Model
    {
        $foreignId = $this->{$foreignKey} ?? null;
        if ($foreignId === null || $foreignId === '') {
            return null;
        }

        /** @var TRelated|null $related */
        $related = $relatedClass::query()->where($ownerKey, $foreignId)->first();

        return $related;
    }

    /**
     * Resolve a one-to-many relation.
     *
     * @template TRelated of Model
     * @param class-string<TRelated> $relatedClass
     * @return list<TRelated>
     */
    protected function hasMany(string $relatedClass, string $foreignKey, string $localKey = 'id'): array
    {
        $localId = $this->{$localKey} ?? null;
        if ($localId === null || $localId === '') {
            return [];
        }

        /** @var list<TRelated> $related */
        $related = $relatedClass::query()->where($foreignKey, $localId)->get();

        return $related;
    }

    /**
     * Resolve a many-to-many relation through a pivot table.
     *
     * @template TRelated of Model
     * @param class-string<TRelated> $relatedClass
     * @return list<TRelated>
     */
    protected function belongsToMany(
        string $relatedClass,
        string $pivotTable,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey = 'id',
        string $relatedKey = 'id',
    ): array {
        $parentId = $this->{$parentKey} ?? null;
        if ($parentId === null || $parentId === '') {
            return [];
        }

        $sql = 'SELECT r.*'
            . ' FROM ' . $relatedClass::table() . ' r'
            . ' INNER JOIN ' . $pivotTable . ' p ON p.' . $relatedPivotKey . ' = r.' . $relatedKey
            . ' WHERE p.' . $foreignPivotKey . ' = ?'
            . ' ORDER BY p.id ASC';
        $rows = static::connection()->select($sql, [$parentId]);
        $out = [];
        foreach ($rows as $row) {
            $out[] = $relatedClass::fromRow($row);
        }

        return $out;
    }

    public function save(): void
    {
        $id = $this->id ?? null;
        if ($id !== null && $id !== '') {
            static::updateRecord((int) $id, $this->gatherFillableFromInstance());

            return;
        }

        $created = static::create($this->gatherFillableFromInstance());
        $this->refreshFromCreated($created);
    }

    /**
     * Mass-assign fillable attributes on this instance and persist (existing row only).
     *
     * @param array<string, mixed> $attributes
     */
    public function update(array $attributes): void
    {
        $id = $this->id ?? null;
        if ($id === null || $id === '') {
            throw new LogicException('Cannot update a model without a primary key.');
        }

        foreach (static::onlyFillable($attributes) as $key => $value) {
            $this->{$key} = $value;
        }

        $this->save();
    }

    public function delete(): void
    {
        $id = $this->id ?? null;
        if ($id === null || $id === '') {
            return;
        }

        static::deleteId((int) $id);
        $this->id = null;
    }

    /**
     * @return array<string, mixed>
     */
    private function gatherFillableFromInstance(): array
    {
        $vars = get_object_vars($this);
        $attrs = [];
        foreach (static::$fillable as $key) {
            if (array_key_exists($key, $vars)) {
                $attrs[$key] = $vars[$key];
            }
        }

        return $attrs;
    }

    private function refreshFromCreated(self $created): void
    {
        $this->id = $created->id;
        foreach (get_object_vars($created) as $k => $v) {
            $this->{$k} = $v;
        }
    }
}
