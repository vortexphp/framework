<?php

declare(strict_types=1);

namespace Vortex\Support;

/**
 * Small row-set operations on {@code list<array<string,mixed>>} (e.g. SQL results).
 */
final class CollectionHelp
{
    /**
     * Last row wins when the same key appears twice.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, array<string, mixed>>
     */
    public static function keyBy(array $rows, string $key): array
    {
        $map = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! array_key_exists($key, $row)) {
                continue;
            }
            $k = $row[$key];
            if (! is_string($k) && ! is_int($k)) {
                continue;
            }
            $map[(string) $k] = $row;
        }

        return $map;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function groupBy(array $rows, string $key): array
    {
        $groups = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! array_key_exists($key, $row)) {
                continue;
            }
            $k = $row[$key];
            if (! is_string($k) && ! is_int($k)) {
                continue;
            }
            $sk = (string) $k;
            if (! isset($groups[$sk])) {
                $groups[$sk] = [];
            }
            $groups[$sk][] = $row;
        }

        return $groups;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<mixed>
     */
    public static function pluck(array $rows, string $key): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row) && array_key_exists($key, $row)) {
                $out[] = $row[$key];
            }
        }

        return $out;
    }
}
