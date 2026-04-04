<?php

declare(strict_types=1);

namespace Vortex\Database;

/**
 * Builders for {@see Model::eagerRelations()} entries (same arrays {@see QueryBuilder::with()} consumes).
 */
final class Relation
{
    private function __construct()
    {
    }

    /**
     * @param class-string<Model> $related
     *
     * @return list<mixed>
     */
    public static function belongsTo(string $related, string $foreignKey, string $ownerKey = 'id'): array
    {
        return $ownerKey === 'id'
            ? ['belongsTo', $related, $foreignKey]
            : ['belongsTo', $related, $foreignKey, $ownerKey];
    }

    /**
     * @param class-string<Model> $related
     *
     * @return list<mixed>
     */
    public static function hasMany(string $related, string $foreignKey, string $localKey = 'id'): array
    {
        return $localKey === 'id'
            ? ['hasMany', $related, $foreignKey]
            : ['hasMany', $related, $foreignKey, $localKey];
    }

    /**
     * Parent has one related row (same FK layout as {@see hasMany()}; eager load keeps the first row per parent by {@code id}).
     *
     * @param class-string<Model> $related
     *
     * @return list<mixed>
     */
    public static function hasOne(string $related, string $foreignKey, string $localKey = 'id'): array
    {
        return $localKey === 'id'
            ? ['hasOne', $related, $foreignKey]
            : ['hasOne', $related, $foreignKey, $localKey];
    }

    /**
     * @param class-string<Model> $related
     *
     * @return list<mixed>
     */
    public static function belongsToMany(
        string $related,
        string $pivotTable,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey = 'id',
        string $relatedKey = 'id',
    ): array {
        $spec = ['belongsToMany', $related, $pivotTable, $foreignPivotKey, $relatedPivotKey];
        if ($parentKey !== 'id') {
            $spec[] = $parentKey;
            if ($relatedKey !== 'id') {
                $spec[] = $relatedKey;
            }

            return $spec;
        }
        if ($relatedKey !== 'id') {
            $spec[] = 'id';
            $spec[] = $relatedKey;
        }

        return $spec;
    }

    /**
     * Inverse polymorphic: child holds `{name}_type` (parent class name) and `{name}_id`.
     *
     * @return list<mixed>
     */
    public static function morphTo(string $name): array
    {
        return ['morphTo', $name];
    }

    /**
     * Parent-owned one-to-many: related rows use `{morphName}_type` (this model class) and `{morphName}_id` (local key).
     *
     * @param class-string<Model> $related
     *
     * @return list<mixed>
     */
    public static function morphMany(string $related, string $morphName, string $localKey = 'id'): array
    {
        return $localKey === 'id'
            ? ['morphMany', $related, $morphName]
            : ['morphMany', $related, $morphName, $localKey];
    }

    /**
     * Same as {@see morphMany()} but at most one related model per parent (lowest {@code id} wins when eager-loading).
     *
     * @param class-string<Model> $related
     *
     * @return list<mixed>
     */
    public static function morphOne(string $related, string $morphName, string $localKey = 'id'): array
    {
        return $localKey === 'id'
            ? ['morphOne', $related, $morphName]
            : ['morphOne', $related, $morphName, $localKey];
    }
}
