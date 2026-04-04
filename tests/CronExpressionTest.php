<?php

declare(strict_types=1);

namespace Vortex\Tests;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vortex\Schedule\CronExpression;

final class CronExpressionTest extends TestCase
{
    public function testEveryMinute(): void
    {
        $at = new DateTimeImmutable('2026-06-15 14:07:33', new DateTimeZone('UTC'));
        self::assertTrue(CronExpression::isDue('* * * * *', $at));
    }

    public function testSpecificMinute(): void
    {
        $at = new DateTimeImmutable('2026-01-01 09:30:00', new DateTimeZone('UTC'));
        self::assertTrue(CronExpression::isDue('30 * * * *', $at));
        self::assertFalse(CronExpression::isDue('31 * * * *', $at));
    }

    public function testStepMinute(): void
    {
        $at = new DateTimeImmutable('2026-01-01 00:10:00', new DateTimeZone('UTC'));
        self::assertTrue(CronExpression::isDue('*/5 * * * *', $at));
        $at2 = new DateTimeImmutable('2026-01-01 00:12:00', new DateTimeZone('UTC'));
        self::assertFalse(CronExpression::isDue('*/5 * * * *', $at2));
    }

    public function testHourlyOnTheHour(): void
    {
        $ok = new DateTimeImmutable('2026-03-10 15:00:01', new DateTimeZone('UTC'));
        self::assertTrue(CronExpression::isDue('0 * * * *', $ok));
        $bad = new DateTimeImmutable('2026-03-10 15:01:00', new DateTimeZone('UTC'));
        self::assertFalse(CronExpression::isDue('0 * * * *', $bad));
    }

    public function testRejectsWrongFieldCount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CronExpression::isDue('* * * *', new DateTimeImmutable('now'));
    }
}
