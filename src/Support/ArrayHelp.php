<?php

declare(strict_types=1);

namespace Vortex\Support;

/**
 * Array helpers for patterns that are not a single native call (dot paths, wrap, pull, etc.).
 */
final class ArrayHelp
{
    /**
     * Read a value using dot paths (e.g. {@code user.meta.id}). Missing segments return {@code $default}.
     */
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $default;
        }

        if (! str_contains($key, '.')) {
            return array_key_exists($key, $array) ? $array[$key] : $default;
        }

        foreach (explode('.', $key) as $segment) {
            if (! is_array($array) || ! array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Whether a dot path exists (present key at each step; {@code null} values count as present).
     */
    public static function has(array $array, string $key): bool
    {
        if ($key === '') {
            return false;
        }

        if (! str_contains($key, '.')) {
            return array_key_exists($key, $array);
        }

        foreach (explode('.', $key) as $segment) {
            if (! is_array($array) || ! array_key_exists($segment, $array)) {
                return false;
            }
            $array = $array[$segment];
        }

        return true;
    }

    /**
     * Assign using a dot path, creating intermediate arrays as needed.
     */
    public static function set(array &$array, string $key, mixed $value): void
    {
        if ($key === '') {
            return;
        }

        $segments = explode('.', $key);
        $last = array_pop($segments);
        $ref = &$array;

        foreach ($segments as $segment) {
            if (! array_key_exists($segment, $ref) || ! is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }

        $ref[$last] = $value;
    }

    /**
     * Read and remove a dot path (or top-level key). Missing path returns {@code $default}.
     */
    public static function pull(array &$array, string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $default;
        }

        if (! str_contains($key, '.')) {
            if (! array_key_exists($key, $array)) {
                return $default;
            }
            $value = $array[$key];
            unset($array[$key]);

            return $value;
        }

        $segments = explode('.', $key);
        $last = array_pop($segments);
        $ref = &$array;

        foreach ($segments as $segment) {
            if (! is_array($ref) || ! array_key_exists($segment, $ref)) {
                return $default;
            }
            $ref = &$ref[$segment];
        }

        if (! is_array($ref) || ! array_key_exists($last, $ref)) {
            return $default;
        }

        $value = $ref[$last];
        unset($ref[$last]);

        return $value;
    }

    /**
     * @return list<mixed>
     */
    public static function wrap(mixed $value): array
    {
        return is_array($value) ? $value : [$value];
    }

    /**
     * Only keys listed in {@code $keys} (missing keys are omitted).
     *
     * @param array<string, mixed> $array
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    public static function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * All keys except those listed.
     *
     * @param array<string, mixed> $array
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    public static function except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }
}
