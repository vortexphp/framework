<?php

declare(strict_types=1);

namespace Vortex\Support;

/**
 * Filesystem path joining and containment checks (complement upload-specific storage APIs).
 */
final class PathHelp
{
    /**
     * Join path segments with {@code /}; normalizes backslashes. Empty or {@code .} segments are skipped.
     */
    public static function join(string ...$parts): string
    {
        $filtered = [];
        foreach ($parts as $part) {
            $part = str_replace('\\', '/', $part);
            foreach (explode('/', $part) as $seg) {
                if ($seg === '' || $seg === '.') {
                    continue;
                }
                if ($seg === '..') {
                    array_pop($filtered);

                    continue;
                }
                $filtered[] = $seg;
            }
        }

        if ($filtered === []) {
            return '';
        }

        $path = implode('/', $filtered);
        if (str_starts_with($parts[0], '/')) {
            return '/' . $path;
        }

        return $path;
    }

    /**
     * Whether {@code $candidate} resolves under {@code $base} (both must exist for {@see realpath}).
     */
    public static function isBelowBase(string $base, string $candidate): bool
    {
        $baseReal = realpath($base);
        $candidateReal = realpath($candidate);
        if ($baseReal === false || $candidateReal === false) {
            return false;
        }

        $baseNorm = rtrim(str_replace('\\', '/', $baseReal), '/') . '/';
        $candNorm = str_replace('\\', '/', $candidateReal);

        return str_starts_with($candNorm, $baseNorm) || $candNorm === rtrim($baseNorm, '/');
    }
}
