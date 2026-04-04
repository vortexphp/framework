<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Container;
use Vortex\Tests\Fixtures\NeedsNoDeps;
use Vortex\Tests\Fixtures\NoDeps;

final class ContainerTest extends TestCase
{
    public function testSingletonReturnsSameInstance(): void
    {
        $c = new Container();
        $c->singleton(NoDeps::class, NoDeps::class);
        $a = $c->make(NoDeps::class);
        $b = $c->make(NoDeps::class);

        self::assertSame($a, $b);
    }

    public function testResolvesConstructorTypeHints(): void
    {
        $c = new Container();
        $c->singleton(NoDeps::class, NoDeps::class);
        $n = $c->make(NeedsNoDeps::class);

        self::assertInstanceOf(NoDeps::class, $n->inner);
    }

    public function testInstanceBinding(): void
    {
        $c = new Container();
        $live = new NoDeps();
        $c->instance(NoDeps::class, $live);

        self::assertSame($live, $c->make(NoDeps::class));
    }

    public function testHas(): void
    {
        $c = new Container();
        self::assertFalse($c->has(NoDeps::class));
        $c->singleton(NoDeps::class, NoDeps::class);
        self::assertTrue($c->has(NoDeps::class));
        self::assertFalse($c->has(NeedsNoDeps::class));
    }
}
