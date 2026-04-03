<?php

declare(strict_types=1);

namespace Vortex\Support;

/**
 * String helpers for common formatting and extraction (slug, truncation, delimited slices, random tokens).
 */
final class StringHelp
{
    /**
     * ASCII-ish slug: lowercase, non-alphanumeric runs become {@code $separator}, trimmed.
     */
    public static function slug(string $value, string $separator = '-'): string
    {
        $value = strtolower(trim($value));

        if (function_exists('transliterator_transliterate')) {
            /** @var string|false $latin */
            $latin = transliterator_transliterate('Any-Latin; Latin-ASCII; NFD; [:Nonspacing Mark:] Remove; NFC', $value);
            if (is_string($latin) && $latin !== '') {
                $value = $latin;
            }
        } elseif (extension_loaded('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        $sep = preg_quote($separator, '/');
        $value = preg_replace('/[^a-z0-9]+/', $separator, $value) ?? '';
        $value = trim($value, $separator);

        return preg_replace('/' . $sep . '+/', $separator, $value) ?? '';
    }

    /**
     * Truncate to {@code $limit} characters; uses mbstring when available.
     */
    public static function limit(string $value, int $limit, string $end = '...'): string
    {
        if ($limit <= 0) {
            return '';
        }

        if (extension_loaded('mbstring')) {
            if (mb_strlen($value) <= $limit) {
                return $value;
            }

            return mb_substr($value, 0, $limit) . $end;
        }

        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, $limit) . $end;
    }

    /**
     * Collapse all whitespace runs to a single space and trim.
     */
    public static function squish(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    /**
     * {@code snake_case} from {@code StudlyCase}, spaces, or existing separators.
     */
    public static function snake(string $value, string $separator = '_'): string
    {
        $value = str_replace(['-', ' '], $separator, $value);
        $value = preg_replace('/(.)(?=[A-Z])/u', '$1' . $separator, $value) ?? $value;

        return strtolower($value);
    }

    /**
     * {@code camelCase} from {@code snake_case} or {@code kebab-case}.
     */
    public static function camel(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords($value, ' ');
        $value = str_replace(' ', '', $value);

        return lcfirst($value);
    }

    /**
     * Substring after the first {@code $needle}, or {@code ''} if {@code $needle} is missing.
     */
    public static function after(string $haystack, string $needle): string
    {
        if ($needle === '') {
            return $haystack;
        }

        $pos = strpos($haystack, $needle);

        return $pos === false ? '' : substr($haystack, $pos + strlen($needle));
    }

    /**
     * Substring before the first {@code $needle}, or the full string if {@code $needle} is missing.
     */
    public static function before(string $haystack, string $needle): string
    {
        if ($needle === '') {
            return $haystack;
        }

        $pos = strpos($haystack, $needle);

        return $pos === false ? $haystack : substr($haystack, 0, $pos);
    }

    /**
     * Text between the first {@code $start} and the next {@code $end}; {@code null} if either delimiter is missing.
     */
    public static function between(string $haystack, string $start, string $end): ?string
    {
        $startPos = strpos($haystack, $start);
        if ($startPos === false) {
            return null;
        }

        $from = $startPos + strlen($start);
        $endPos = strpos($haystack, $end, $from);
        if ($endPos === false) {
            return null;
        }

        return substr($haystack, $from, $endPos - $from);
    }

    /**
     * Cryptographically random alphanumeric string (hex alphabet, length {@code $length}).
     */
    public static function random(int $length = 32): string
    {
        if ($length <= 0) {
            return '';
        }

        $bytes = (int) ceil($length / 2);

        return substr(bin2hex(random_bytes($bytes)), 0, $length);
    }
}
