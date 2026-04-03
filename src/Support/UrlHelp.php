<?php

declare(strict_types=1);

namespace Vortex\Support;

/**
 * Query strings and path+query composition for redirects and links.
 */
final class UrlHelp
{
    /**
     * Append or merge query parameters onto {@code $path}. Preserves an existing {@code #fragment}.
     *
     * @param array<string, string|int|float|bool|null> $query
     */
    public static function withQuery(string $path, array $query): string
    {
        if ($query === []) {
            return $path;
        }

        $fragment = '';
        $hashPos = strpos($path, '#');
        if ($hashPos !== false) {
            $fragment = substr($path, $hashPos);
            $path = substr($path, 0, $hashPos);
        }

        $built = http_build_query($query);
        if ($built === '') {
            return $path . $fragment;
        }

        $sep = str_contains($path, '?') ? '&' : '?';

        return $path . $sep . $built . $fragment;
    }

    /**
     * Strip {@code #fragment} for comparisons or canonicalization.
     */
    public static function withoutFragment(string $url): string
    {
        $pos = strpos($url, '#');

        return $pos === false ? $url : substr($url, 0, $pos);
    }

    /**
     * True if {@code $path} is a same-origin path (leading slash, not scheme-relative).
     */
    public static function isInternalPath(string $path): bool
    {
        return $path !== ''
            && str_starts_with($path, '/')
            && ! str_starts_with($path, '//');
    }
}
