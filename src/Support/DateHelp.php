<?php

declare(strict_types=1);

namespace Vortex\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

/**
 * Immutable dates without a third-party clock library.
 */
final class DateHelp
{
    /**
     * Current instant in {@code $timezone}, or PHP default when {@code null}.
     *
     * @throws Exception
     */
    public static function now(?string $timezone = null): DateTimeImmutable
    {
        $tz = $timezone !== null && $timezone !== ''
            ? new DateTimeZone($timezone)
            : new DateTimeZone(date_default_timezone_get());

        return new DateTimeImmutable('now', $tz);
    }

    public static function toRfc3339(DateTimeInterface $at): string
    {
        return $at->format(DateTimeInterface::ATOM);
    }

    /**
     * Format for HTTP {@code Date} header (RFC 7231 IMF-fixdate).
     */
    public static function toHttpDate(DateTimeInterface $at): string
    {
        $i = DateTimeImmutable::createFromInterface($at);

        return $i->setTimezone(new DateTimeZone('GMT'))->format('D, d M Y H:i:s') . ' GMT';
    }
}
