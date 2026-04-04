<?php

declare(strict_types=1);

namespace Vortex\Database;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonException;
use LogicException;
use Vortex\AppContext;

#[\AllowDynamicProperties]
abstract class Model
{
    protected static ?string $table = null;

    /** @var list<string> */
    protected static array $fillable = [];

    protected static bool $timestamps = true;

    /**
     * When true, {@see delete()} sets {@see $deletedAtColumn} instead of removing the row.
     */
    protected static bool $softDeletes = false;

    /** @var non-empty-string Timestamp column used by soft deletes (SQL NULL = not deleted). */
    protected static string $deletedAtColumn = 'deleted_at';

    /**
     * Attribute casts: key => type. Supported: {@code int}, {@code float}, {@code bool}, {@code string},
     * {@code array} / {@code json}, {@code datetime}.
     *
     * @var array<string, string>
     */
    protected static array $casts = [];

    /** @var array<string, list<object>> Observers keyed by concrete model class name. */
    private static array $observerRegistry = [];

    /**
     * Global query constraints per concrete model class ({@see addGlobalScope}).
     *
     * @var array<class-string, array<string, callable(QueryBuilder): void>>
     */
    private static array $globalScopeRegistry = [];

    /**
     * Register an observer instance or class (instantiated with {@code new}) for this model type.
     *
     * Handlers are optional methods on the observer: {@code saving}, {@code creating}, {@code updating},
     * {@code deleting} (before persistence) and {@code saved}, {@code created}, {@code updated}, {@code deleted} (after).
     *
     * @param class-string|object $observer
     */
    public static function observe(string|object $observer): void
    {
        $instance = is_string($observer) ? new $observer() : $observer;
        $modelClass = static::class;
        self::$observerRegistry[$modelClass] ??= [];
        self::$observerRegistry[$modelClass][] = $instance;
    }

    /**
     * @internal Resets all observer registrations (test harness).
     */
    public static function forgetRegisteredObservers(): void
    {
        self::$observerRegistry = [];
    }

    public static function connection(): Connection
    {
        return AppContext::container()->make(Connection::class);
    }

    /**
     * @param callable(QueryBuilder): void $callback
     */
    public static function addGlobalScope(string $name, callable $callback): void
    {
        $modelClass = static::class;
        self::$globalScopeRegistry[$modelClass] ??= [];
        self::$globalScopeRegistry[$modelClass][$name] = $callback;
    }

    /**
     * @internal Drops global scopes for this concrete model class (tests).
     */
    public static function forgetGlobalScopes(): void
    {
        unset(self::$globalScopeRegistry[static::class]);
    }

    /**
     * @internal
     */
    public static function forgetAllGlobalScopesForTesting(): void
    {
        self::$globalScopeRegistry = [];
    }

    public static function query(): QueryBuilder
    {
        $q = new QueryBuilder(static::class);
        foreach (self::$globalScopeRegistry[static::class] ?? [] as $name => $callback) {
            $q->applyGlobalScope($name, $callback);
        }

        return $q;
    }

    /**
     * Class / alias stored in polymorphic {@code morph_type} columns for this model (see {@see MorphMap::register()}).
     */
    public static function getMorphClass(): string
    {
        return MorphMap::morphAliasFor(static::class);
    }

    public static function usesSoftDeletes(): bool
    {
        return static::$softDeletes;
    }

    /**
     * @return non-empty-string|null
     */
    public static function softDeleteColumn(): ?string
    {
        return static::$softDeletes ? static::$deletedAtColumn : null;
    }

    public static function usesTimestamps(): bool
    {
        return static::$timestamps;
    }

    /**
     * Eager-load definitions for {@see QueryBuilder::with()}. Keys match public relation method names (use dot paths for nested eager loads, e.g. {@code author} on the root model and {@code country} on the related author model).
     *
     * Each value is one of:
     * - `['belongsTo', Related::class, foreignKey, ownerKey?]` (owner key defaults to {@code id}), or {@see Relation::belongsTo()}
     * - `['hasMany', Related::class, foreignKey, localKey?]` (local key defaults to {@code id}), or {@see Relation::hasMany()}
     * - `['hasOne', Related::class, foreignKey, localKey?]` (same as {@code hasMany} shape; at most one related model per parent), or {@see Relation::hasOne()}
     * - `['belongsToMany', Related::class, pivotTable, foreignPivotKey, relatedPivotKey, parentKey?, relatedKey?]`, or {@see Relation::belongsToMany()}
     * - `['morphTo', namePrefix]` — columns `{namePrefix}_type` / `{namePrefix}_id` (type column holds {@see MorphMap} aliases or FQCNs), or {@see Relation::morphTo()}
     * - `['morphMany', Related::class, morphName, localKey?]` — child columns `{morphName}_type` / `{morphName}_id`, or {@see Relation::morphMany()}
     * - `['morphOne', Related::class, morphName, localKey?]`, or {@see Relation::morphOne()}
     *
     * @return array<string, list<mixed>>
     */
    protected static function eagerRelations(): array
    {
        return [];
    }

    /**
     * @internal
     * @return list<mixed>|null
     */
    public static function eagerRelationSpec(string $name): ?array
    {
        $map = static::eagerRelations();

        return $map[$name] ?? null;
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
        return static::query()->get();
    }

    public static function find(mixed $id): ?static
    {
        return static::query()->where('id', $id)->first();
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

        $model = static::fromRow($filtered);
        $model->performInsert();

        return $model;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): static
    {
        $m = new static();
        foreach ($row as $k => $v) {
            $m->{$k} = static::castFromDatabase((string) $k, $v);
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

        foreach ($filtered as $key => $value) {
            $filtered[$key] = static::castForDatabase((string) $key, $value);
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

        $sql = 'UPDATE ' . static::table() . ' SET ' . $set . ' WHERE id = ?';
        if (($col = static::softDeleteColumn()) !== null) {
            $sql .= ' AND ' . $col . ' IS NULL';
        }
        static::connection()->execute($sql, $bindings);
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
     * Resolve a one-to-one relation where the related model holds the foreign key (inverse of {@see belongsTo()} on the child).
     *
     * @template TRelated of Model
     * @param class-string<TRelated> $relatedClass
     * @return TRelated|null
     */
    protected function hasOne(string $relatedClass, string $foreignKey, string $localKey = 'id'): ?Model
    {
        $localId = $this->{$localKey} ?? null;
        if ($localId === null || $localId === '') {
            return null;
        }

        /** @var TRelated|null $related */
        $related = $relatedClass::query()->where($foreignKey, $localId)->orderBy('id')->first();

        return $related;
    }

    /**
     * Polymorphic inverse: `{name}_type` is a {@see MorphMap} alias or FQCN; `{name}_id` is that model’s primary key (default {@code id}).
     *
     * @template TRelated of Model
     * @return TRelated|null
     */
    protected function morphTo(string $name): ?Model
    {
        $typeKey = $name . '_type';
        $idKey = $name . '_id';
        $stored = $this->{$typeKey} ?? null;
        $foreignId = $this->{$idKey} ?? null;
        if (! is_string($stored) || $stored === '' || $foreignId === null || $foreignId === '') {
            return null;
        }
        $relatedClass = MorphMap::resolveClass($stored);
        if ($relatedClass === null) {
            return null;
        }

        /** @var class-string<Model> $relatedClass */
        /** @var TRelated|null $related */
        $related = $relatedClass::query()->where('id', $foreignId)->first();

        return $related;
    }

    /**
     * One-to-many polymorphic: related rows store this model’s class in `{morphName}_type` and the local key in `{morphName}_id`.
     *
     * @template TRelated of Model
     * @param class-string<TRelated> $relatedClass
     * @return list<TRelated>
     */
    protected function morphMany(string $relatedClass, string $morphName, string $localKey = 'id'): array
    {
        $localId = $this->{$localKey} ?? null;
        if ($localId === null || $localId === '') {
            return [];
        }
        $typeCol = $morphName . '_type';
        $idCol = $morphName . '_id';

        /** @var list<TRelated> $related */
        $related = $relatedClass::query()
            ->where($typeCol, static::getMorphClass())
            ->where($idCol, $localId)
            ->orderBy('id')
            ->get();

        return $related;
    }

    /**
     * @template TRelated of Model
     * @param class-string<TRelated> $relatedClass
     * @return TRelated|null
     */
    protected function morphOne(string $relatedClass, string $morphName, string $localKey = 'id'): ?Model
    {
        $localId = $this->{$localKey} ?? null;
        if ($localId === null || $localId === '') {
            return null;
        }
        $typeCol = $morphName . '_type';
        $idCol = $morphName . '_id';

        /** @var TRelated|null $related */
        $related = $relatedClass::query()
            ->where($typeCol, static::getMorphClass())
            ->where($idCol, $localId)
            ->orderBy('id')
            ->first();

        return $related;
    }

    /**
     * Eager-load relations onto this instance (dot paths supported). Uses the same batching as {@see QueryBuilder::with()}.
     *
     * @param string|list<string> $relations
     * @return $this
     */
    public function load(string|array $relations): static
    {
        $rels = is_array($relations) ? $relations : [$relations];
        $rels = array_values(array_filter(
            $rels,
            static fn (mixed $r): bool => is_string($r) && $r !== '',
        ));
        if ($rels === []) {
            return $this;
        }

        static::query()->with($rels)->eagerLoadOnto([$this]);

        return $this;
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
            $this->fireModelEvent('saving');
            $this->fireModelEvent('updating');
            static::updateRecord((int) $id, $this->gatherFillableFromInstance());
            $this->fireModelEvent('updated');
            $this->fireModelEvent('saved');

            return;
        }

        if (static::$timestamps) {
            $now = date('Y-m-d H:i:s');
            if (static::$fillable === [] || in_array('created_at', static::$fillable, true)) {
                if (! isset($this->created_at)) {
                    $this->created_at = $now;
                }
            }
            if (static::$fillable === [] || in_array('updated_at', static::$fillable, true)) {
                if (! isset($this->updated_at)) {
                    $this->updated_at = $now;
                }
            }
        }

        $this->performInsert();
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

        $this->fireModelEvent('deleting');

        if (static::$softDeletes) {
            $col = static::$deletedAtColumn;
            $now = date('Y-m-d H:i:s');
            if (static::$timestamps) {
                static::connection()->execute(
                    'UPDATE ' . static::table()
                    . ' SET ' . $col . ' = ?, updated_at = ? WHERE id = ? AND ' . $col . ' IS NULL',
                    [$now, $now, (int) $id],
                );
                $this->updated_at = $now;
            } else {
                static::connection()->execute(
                    'UPDATE ' . static::table()
                    . ' SET ' . $col . ' = ? WHERE id = ? AND ' . $col . ' IS NULL',
                    [$now, (int) $id],
                );
            }
            $this->{$col} = $now;
        } else {
            static::deleteId((int) $id);
            $this->id = null;
        }

        $this->fireModelEvent('deleted');
    }

    /**
     * Permanently remove the row (ignores soft-delete column).
     */
    public function forceDelete(): void
    {
        $id = $this->id ?? null;
        if ($id === null || $id === '') {
            return;
        }

        $this->fireModelEvent('deleting');
        static::deleteId((int) $id);
        $this->fireModelEvent('deleted');
        $this->id = null;
    }

    /**
     * Clear soft-delete timestamp for this instance (must exist in DB and be trashed).
     */
    public function restore(): void
    {
        if (! static::$softDeletes) {
            throw new LogicException(static::class . ' does not use soft deletes.');
        }

        $id = $this->id ?? null;
        if ($id === null || $id === '') {
            return;
        }

        $col = static::$deletedAtColumn;
        if (static::$timestamps) {
            $now = date('Y-m-d H:i:s');
            static::connection()->execute(
                'UPDATE ' . static::table()
                . ' SET ' . $col . ' = NULL, updated_at = ? WHERE id = ? AND ' . $col . ' IS NOT NULL',
                [$now, (int) $id],
            );
            $this->updated_at = $now;
        } else {
            static::connection()->execute(
                'UPDATE ' . static::table()
                . ' SET ' . $col . ' = NULL WHERE id = ? AND ' . $col . ' IS NOT NULL',
                [(int) $id],
            );
        }

        $this->{$col} = null;
    }

    /**
     * @return array<string, mixed>
     */
    private function gatherFillableFromInstance(): array
    {
        $vars = get_object_vars($this);
        if (static::$fillable === []) {
            $attrs = $vars;
        } else {
            $attrs = [];
            foreach (static::$fillable as $key) {
                if (array_key_exists($key, $vars)) {
                    $attrs[$key] = $vars[$key];
                }
            }
        }

        foreach ($attrs as $key => $value) {
            $attrs[$key] = static::castForDatabase((string) $key, $value);
        }

        return $attrs;
    }

    private function performInsert(): void
    {
        $this->fireModelEvent('saving');
        $this->fireModelEvent('creating');

        $payload = $this->gatherFillableFromInstance();
        if (array_key_exists('id', $payload) && ($payload['id'] === null || $payload['id'] === '')) {
            unset($payload['id']);
        }
        if ($payload === []) {
            throw new LogicException('Cannot insert a model with no fillable attributes set.');
        }

        $cols = array_keys($payload);
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $colList = implode(', ', $cols);
        $sql = 'INSERT INTO ' . static::table() . " ({$colList}) VALUES ({$placeholders})";
        static::connection()->execute($sql, array_values($payload));
        $id = (int) static::connection()->lastInsertId();
        $this->id = $id;

        $fresh = static::find($id);
        if ($fresh !== null) {
            foreach (get_object_vars($fresh) as $k => $v) {
                $this->{$k} = $v;
            }
        }

        $this->fireModelEvent('created');
        $this->fireModelEvent('saved');
    }

    protected function fireModelEvent(string $event): void
    {
        foreach (self::$observerRegistry[static::class] ?? [] as $observer) {
            if (method_exists($observer, $event)) {
                $observer->{$event}($this);
            }
        }
    }

    private static function castFromDatabase(string $key, mixed $value): mixed
    {
        if ($value === null || ! isset(static::$casts[$key])) {
            return $value;
        }

        $type = strtolower(trim(static::$casts[$key]));

        return match ($type) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => static::castToBool($value),
            'array', 'json' => static::castJsonFromDatabase($value),
            'datetime' => static::castDateTimeFromDatabase($value),
            default => throw new InvalidArgumentException('Unsupported cast [' . $type . '] for attribute [' . $key . '].'),
        };
    }

    private static function castForDatabase(string $key, mixed $value): mixed
    {
        if ($value === null || ! isset(static::$casts[$key])) {
            return $value;
        }

        $type = strtolower(trim(static::$casts[$key]));

        return match ($type) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => ((bool) $value) ? 1 : 0,
            'array', 'json' => static::castJsonForDatabase($value),
            'datetime' => static::castDateTimeForDatabase($value),
            default => throw new InvalidArgumentException('Unsupported cast [' . $type . '] for attribute [' . $key . '].'),
        };
    }

    private static function castToBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value !== 0;
        }

        if ($value === '') {
            return false;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $filtered ?? ((string) $value !== '0');
    }

    /**
     * @return array<mixed>|null
     */
    private static function castJsonFromDatabase(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value === '') {
            return null;
        }

        try {
            /** @var array<mixed>|null $decoded */
            $decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return $decoded;
    }

    private static function castJsonForDatabase(mixed $value): string
    {
        if ($value instanceof \JsonSerializable) {
            return json_encode($value->jsonSerialize(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }
        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    private static function castDateTimeFromDatabase(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof DateTime) {
            return DateTimeImmutable::createFromMutable($value);
        }

        return new DateTimeImmutable((string) $value);
    }

    private static function castDateTimeForDatabase(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }
}
