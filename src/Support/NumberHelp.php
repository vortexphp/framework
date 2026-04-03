<?php

declare(strict_types=1);

namespace Vortex\Support;

/**
 * Numeric bounds, parsing, and human-readable byte sizes.
 */
final class NumberHelp
{
    public static function clamp(int|float $value, int|float $min, int|float $max): int|float
    {
        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }

    /**
     * Parse decimal integer string; out-of-range or non-numeric values return {@code $default}.
     */
    public static function parseInt(?string $value, int $default, int $min, int $max): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (! preg_match('/^-?\d+$/', $value)) {
            return $default;
        }

        $n = (int) $value;

        return (int) self::clamp($n, $min, $max);
    }

    public static function formatBytes(int $bytes, int $precision = 1): string
    {
        $bytes = max(0, $bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $v = (float) $bytes;
        while ($v >= 1024 && $i < count($units) - 1) {
            $v /= 1024;
            ++$i;
        }

        if ($i === 0) {
            return (string) $bytes . ' B';
        }

        return round($v, $precision) . ' ' . $units[$i];
    }
}
