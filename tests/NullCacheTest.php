<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Cache\NullCache;

final class NullCacheTest extends TestCase
{
    public function testAlwaysMissesAndRememberInvokesCallback(): void
    {
        $c = new NullCache();
        self::assertSame('d', $c->get('any', 'd'));
        $n = 0;
        self::assertSame(3, $c->remember('k', 60, function () use (&$n): int {
            $n++;

            return 3;
        }));
        self::assertSame(6, $c->remember('k', 60, function () use (&$n): int {
            $n++;

            return 6;
        }));
        self::assertSame(2, $n);
    }
}
