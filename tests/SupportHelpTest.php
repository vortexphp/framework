<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Support\ArrayHelp;
use Vortex\Support\StringHelp;

final class SupportHelpTest extends TestCase
{
    public function testArrayGetHasSetPull(): void
    {
        $data = ['a' => 1, 'n' => ['x' => ['y' => 2]]];

        self::assertSame(2, ArrayHelp::get($data, 'n.x.y'));
        self::assertSame('d', ArrayHelp::get($data, 'missing', 'd'));
        self::assertTrue(ArrayHelp::has($data, 'n.x.y'));
        self::assertFalse(ArrayHelp::has($data, 'n.x.z'));

        ArrayHelp::set($data, 'n.x.z', 3);
        self::assertSame(3, ArrayHelp::get($data, 'n.x.z'));

        self::assertSame(3, ArrayHelp::pull($data, 'n.x.z'));
        self::assertFalse(ArrayHelp::has($data, 'n.x.z'));
    }

    public function testArrayWrapOnlyExcept(): void
    {
        self::assertSame([1], ArrayHelp::wrap(1));
        self::assertSame([1], ArrayHelp::wrap([1]));

        $row = ['a' => 1, 'b' => 2, 'c' => 3];
        self::assertSame(['a' => 1, 'c' => 3], ArrayHelp::only($row, ['a', 'c']));
        self::assertSame(['b' => 2], ArrayHelp::except($row, ['a', 'c']));
    }

    public function testStringSlugLimitSquish(): void
    {
        self::assertSame('hello-world', StringHelp::slug('Hello, World!!'));
        self::assertSame('ab...', StringHelp::limit('abcd', 2));
        self::assertSame('a b', StringHelp::squish("  a  \n b\t"));
    }

    public function testStringSnakeCamel(): void
    {
        self::assertSame('foo_bar', StringHelp::snake('FooBar'));
        self::assertSame('foo_bar', StringHelp::snake('foo bar'));
        self::assertSame('fooBar', StringHelp::camel('foo_bar'));
    }

    public function testStringAfterBeforeBetween(): void
    {
        self::assertSame('b', StringHelp::after('a=b', '='));
        self::assertSame('a', StringHelp::before('a=b', '='));
        self::assertSame('tail', StringHelp::after('pre-tail', '-'));
        self::assertSame('mid', StringHelp::between('[mid]', '[', ']'));
        self::assertNull(StringHelp::between('x', '[', ']'));
    }

    public function testStringRandomLength(): void
    {
        $s = StringHelp::random(12);
        self::assertSame(12, strlen($s));
        self::assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $s);
    }
}
