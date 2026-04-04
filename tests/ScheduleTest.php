<?php

declare(strict_types=1);

namespace Vortex\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Config\Repository;
use Vortex\Container;
use Vortex\Schedule\Schedule;

final class ScheduleTest extends TestCase
{
    protected function tearDown(): void
    {
        Schedule::resetForTesting();
        Repository::forgetInstance();
        $ref = new \ReflectionClass(AppContext::class);
        $p = $ref->getProperty('container');
        $p->setAccessible(true);
        $p->setValue(null, null);

        ScheduleHeartbeat::$hits = 0;
        ScheduleHandle::$hits = 0;

        parent::tearDown();
    }

    public function testRunDueInvokesCallableHandler(): void
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        AppContext::set($c);

        Schedule::register('* * * * *', ScheduleHeartbeat::class);

        $at = new DateTimeImmutable('2026-04-04 12:30:00', new DateTimeZone('UTC'));
        self::assertSame(1, Schedule::runDue($at));
        self::assertSame(1, ScheduleHeartbeat::$hits);
    }

    public function testSkipsWhenCronDoesNotMatch(): void
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        AppContext::set($c);

        Schedule::register('0 * * * *', ScheduleHeartbeat::class);
        $at = new DateTimeImmutable('2026-04-04 12:30:00', new DateTimeZone('UTC'));
        self::assertSame(0, Schedule::runDue($at));
        self::assertSame(0, ScheduleHeartbeat::$hits);
    }

    public function testHandleMethodIsInvoked(): void
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        AppContext::set($c);

        Schedule::register('* * * * *', ScheduleHandle::class);
        $at = new DateTimeImmutable('2026-04-04 00:00:00', new DateTimeZone('UTC'));
        self::assertSame(1, Schedule::runDue($at));
        self::assertSame(1, ScheduleHandle::$hits);
    }
}

final class ScheduleHeartbeat
{
    public static int $hits = 0;

    public function __invoke(): void
    {
        self::$hits++;
    }
}

final class ScheduleHandle
{
    public static int $hits = 0;

    public function handle(): void
    {
        self::$hits++;
    }
}
