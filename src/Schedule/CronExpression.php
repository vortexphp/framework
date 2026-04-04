<?php

declare(strict_types=1);

namespace Vortex\Schedule;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * Minimal five-field cron (minute hour day-of-month month day-of-week).
 *
 * Supported per field: {@code *}, a plain integer, a step {@code * / n} (no space before slash),
 * comma lists (e.g. {@code 1,15,30}), and hyphen ranges (e.g. {@code 1-5}). Steps inside ranges are not supported.
 */
final class CronExpression
{
    public static function isDue(string $expression, DateTimeInterface $at): bool
    {
        $parts = preg_split('/\s+/', trim($expression));
        if ($parts === false || count($parts) !== 5) {
            throw new InvalidArgumentException('Cron expression must have exactly five fields: minute hour day-of-month month day-of-week.');
        }

        $minute = (int) $at->format('i');
        $hour = (int) $at->format('G');
        $day = (int) $at->format('j');
        $month = (int) $at->format('n');
        $weekday = (int) $at->format('w');

        return self::matchPart($parts[0], $minute)
            && self::matchPart($parts[1], $hour)
            && self::matchPart($parts[2], $day)
            && self::matchPart($parts[3], $month)
            && self::matchPart($parts[4], $weekday);
    }

    private static function matchPart(string $part, int $value): bool
    {
        $part = trim($part);
        if ($part === '*') {
            return true;
        }

        if (preg_match('#^\*/(\d+)$#', $part, $m)) {
            $step = max(1, (int) $m[1]);

            return $value % $step === 0;
        }

        if (str_contains($part, ',')) {
            foreach (explode(',', $part) as $segment) {
                $segment = trim($segment);
                if ($segment === '') {
                    continue;
                }
                if (self::matchPart($segment, $value)) {
                    return true;
                }
            }

            return false;
        }

        if (preg_match('#^(\d+)-(\d+)$#', $part, $m)) {
            $a = (int) $m[1];
            $b = (int) $m[2];

            return $value >= min($a, $b) && $value <= max($a, $b);
        }

        if (ctype_digit($part)) {
            return $value === (int) $part;
        }

        throw new InvalidArgumentException('Unsupported cron field value [' . $part . ']; use *, an integer, */step, a-b, or lists.');
    }
}
