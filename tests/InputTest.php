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
    }

    public function testCommandNullWhenMissing(): void
    {
        $in = Input::fromArgv(['bin/php']);

        self::assertNull($in->command());
        self::assertSame([], $in->tokens());
    }
}
