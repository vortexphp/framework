<?php

declare(strict_types=1);

namespace Vortex\Database;

use InvalidArgumentException;

/**
 * Optional alias map for polymorphic {@code *_type} columns: store short keys (e.g. {@code posts}) instead of FQCNs.
 * Register at boot with {@see register()}; {@see Model::getMorphClass()} and {@see resolveClass()} apply on read/write.
 */
final class MorphMap
{
    /** @var array<string, class-string<Model>> */
    private static array $aliasToClass = [];

    /** @var array<class-string<Model>, string> */
    private static array $classToAlias = [];

    /**
     * @param array<string, class-string<Model>> $map
     */
    public static function register(array $map): void
    {
        foreach ($map as $alias => $class) {
            if (! is_string($alias) || $alias === '') {
                throw new InvalidArgumentException('Morph map alias must be a non-empty string.');
            }
            if (! is_subclass_of($class, Model::class)) {
                throw new InvalidArgumentException("Morph map target [{$class}] must extend " . Model::class . '.');
            }
            if (isset(self::$aliasToClass[$alias]) && self::$aliasToClass[$alias] !== $class) {
                unset(self::$classToAlias[self::$aliasToClass[$alias]]);
            }
            $prev = self::$classToAlias[$class] ?? null;
            if ($prev !== null && $prev !== $alias) {
                unset(self::$aliasToClass[$prev]);
            }
            self::$aliasToClass[$alias] = $class;
            self::$classToAlias[$class] = $alias;
        }
    }

    /**
     * Value written to {@code morphName_type} for this model ({@see morphMany} / {@see morphOne} parents).
     *
     * @param class-string<Model> $class
     */
    public static function morphAliasFor(string $class): string
    {
        return self::$classToAlias[$class] ?? $class;
    }

    /**
     * Resolve a stored {@code *_type} string to a concrete {@see Model} class, or {@code null} if unknown.
     *
     * @return null|class-string<Model>
     */
    public static function resolveClass(string $stored): ?string
    {
        if (isset(self::$aliasToClass[$stored])) {
            return self::$aliasToClass[$stored];
        }
        if (class_exists($stored) && is_subclass_of($stored, Model::class)) {
            /** @var class-string<Model> */
            return $stored;
        }

        return null;
    }

    /**
     * @internal
     */
    public static function clearForTesting(): void
    {
        self::$aliasToClass = [];
        self::$classToAlias = [];
    }
}
