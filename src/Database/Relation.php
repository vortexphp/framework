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
}
