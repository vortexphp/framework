<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Console\Input;

final class InputTest extends TestCase
{
    public function testCommandAndTokens(): void
    {
        $in = Input::fromArgv(['power', 'smoke', 'https://ex.test', '--verbose']);

        self::assertSame('power', $in->script());
        self::assertSame('smoke', $in->command());
        self::assertSame(['https://ex.test', '--verbose'], $in->tokens());
        self::assertSame(['https://ex.test'], $in->arguments());
        self::assertTrue($in->flag('verbose'));
    }

    public function testCommandNullWhenMissing(): void
    {
        $in = Input::fromArgv(['bin/php']);

        self::assertNull($in->command());
        self::assertSame([], $in->tokens());
        self::assertSame([], $in->arguments());
        self::assertSame([], $in->options());
    }

    public function testLongOptionEqualsAndSpacedValue(): void
    {
        $in = Input::fromArgv(['p', 'cmd', '--env=prod', '--name', 'Beta', 'arg1']);
        self::assertSame(['arg1'], $in->arguments());
        self::assertSame('prod', $in->option('env'));
        self::assertSame('Beta', $in->option('name'));
    }

    public function testLongFlagAndShortCluster(): void
    {
        $in = Input::fromArgv(['p', 'x', '-vv', '-abc', 'pos']);
        self::assertTrue($in->flag('v'));
        self::assertTrue($in->flag('a'));
        self::assertTrue($in->flag('b'));
        self::assertTrue($in->flag('c'));
        self::assertSame(['pos'], $in->arguments());
    }

    public function testDoubleDashEndsOptionParsing(): void
    {
        // Without `--` after `--keep`, the next token `a` would be the option value (--keep a).
        $in = Input::fromArgv(['p', 'c', '--keep', '--', 'a', '--other', 'b']);
        self::assertTrue($in->flag('keep'));
        self::assertSame(['a', '--other', 'b'], $in->arguments());
    }

    public function testNumericDashTokenIsPositional(): void
    {
        $in = Input::fromArgv(['p', 'c', '-1', 'edge']);
        self::assertSame(['-1', 'edge'], $in->arguments());
        self::assertSame([], $in->options());
    }

    public function testArgumentIndex(): void
    {
        $in = Input::fromArgv(['p', 'c', 'one', 'two']);
        self::assertSame('one', $in->argument(0));
        self::assertSame('two', $in->argument(1));
        self::assertNull($in->argument(2));
        self::assertSame('def', $in->argument(2, 'def'));
    }

    public function testOptionDefault(): void
    {
        $in = Input::fromArgv(['p', 'c']);
        self::assertNull($in->option('missing'));
        self::assertSame('x', $in->option('missing', 'x'));
        self::assertFalse($in->flag('missing'));
    }
}
