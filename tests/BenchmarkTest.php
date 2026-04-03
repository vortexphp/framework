<?php

declare(strict_types=1);

namespace Vortex\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vortex\Support\Benchmark;

final class BenchmarkTest extends TestCase
{
    protected function tearDown(): void
    {
        Benchmark::forget();
        parent::tearDown();
    }

    public function testStartAndElapsed(): void
    {
        Benchmark::start('db');
        usleep(1_000);

        self::assertTrue(Benchmark::has('db'));
        self::assertGreaterThanOrEqual(0.5, Benchmark::elapsedMs('db'));
        self::assertGreaterThanOrEqual(0.0005, Benchmark::elapsedSeconds('db'));
        self::assertGreaterThan(0, Benchmark::elapsedNs('db'));
    }

    public function testElapsedWithoutStartThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Benchmark not started: missing');
        Benchmark::elapsedMs('missing');
    }

    public function testMeasureReturnsResultAndDuration(): void
    {
        $measured = Benchmark::measure(static function (): int {
            usleep(1_000);

            return 42;
        });

        self::assertSame(42, $measured['result']);
        self::assertArrayHasKey('elapsed_ms', $measured);
        self::assertIsFloat($measured['elapsed_ms']);
        self::assertGreaterThanOrEqual(0.5, $measured['elapsed_ms']);
    }

    public function testForgetByNameAndForgetAll(): void
    {
        Benchmark::start('a');
        Benchmark::start('b');

        Benchmark::forget('a');
        self::assertFalse(Benchmark::has('a'));
        self::assertTrue(Benchmark::has('b'));

        Benchmark::forget();
        self::assertFalse(Benchmark::has('b'));
    }
}
