<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Container;
use Vortex\Tests\Fixtures\AltLeaf;
use Vortex\Tests\Fixtures\InvokableSum;
use Vortex\Tests\Fixtures\NeedsNoDeps;
use Vortex\Tests\Fixtures\NoDeps;
use Vortex\Tests\Fixtures\OrphanInterface;
use Vortex\Tests\Fixtures\StaticAdd;
use Vortex\Tests\Fixtures\UnionLeafCtor;
use Vortex\Tests\Fixtures\UnionSecondWins;

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

    public function testCallInjectsClosureParameters(): void
    {
        $c = new Container();
        $c->singleton(NoDeps::class, NoDeps::class);
        $got = $c->call(static fn (NoDeps $n) => $n);

        self::assertInstanceOf(NoDeps::class, $got);
    }

    public function testCallNamedOverridesAndDefaults(): void
    {
        $c = new Container();
        $c->singleton(NoDeps::class, NoDeps::class);
        $sum = $c->call(
            static fn (NoDeps $n, int $a, int $b = 4): int => $a + $b,
            ['a' => 2],
        );

        self::assertSame(6, $sum);
    }

    public function testCallVariadicFromNamedArray(): void
    {
        $c = new Container();
        $c->singleton(NoDeps::class, NoDeps::class);
        $sum = $c->call(
            static fn (NoDeps $n, int ...$xs): int => array_sum($xs),
            ['xs' => [2, 3, 4]],
        );

        self::assertSame(9, $sum);
    }

    public function testCallInvokableAndStatic(): void
    {
        $c = new Container();
        $c->singleton(NoDeps::class, NoDeps::class);
        $inv = $c->make(InvokableSum::class);

        self::assertSame(12, $c->call($inv, ['a' => 5, 'b' => 7]));

        $r = $c->call([StaticAdd::class, 'combine'], ['x' => 3, 'y' => 8]);
        self::assertSame(11, $r);
    }

    public function testUnionConstructorUsesFirstResolvableMember(): void
    {
        $c = new Container();
        $c->singleton(NoDeps::class, NoDeps::class);
        $c->singleton(AltLeaf::class, AltLeaf::class);
        $u = $c->make(UnionLeafCtor::class);

        self::assertInstanceOf(NoDeps::class, $u->leaf);
    }

    public function testUnionConstructorFallsThroughWhenFirstMemberNotResolvable(): void
    {
        $c = new Container();
        $c->singleton(AltLeaf::class, AltLeaf::class);
        $u = $c->make(UnionSecondWins::class);

        self::assertInstanceOf(AltLeaf::class, $u->pick);
    }

    public function testNullableDefaultNullSkipsUnresolvableInterface(): void
    {
        $c = new Container();
        $got = $c->call(static fn (?OrphanInterface $x = null) => $x);

        self::assertNull($got);
    }
}
