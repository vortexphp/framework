<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Support\Env;

final class EnvTest extends TestCase
{
    public function testLoadParsesQuotedValues(): void
    {
        $path = sys_get_temp_dir() . '/vortex -env-' . bin2hex(random_bytes(4));
        file_put_contents(
            $path,
            "FOO_BAR=hello\n# comment\nEMPTY=\nQUOTED=\"say \\\"hi\\\"\"\n",
        );

        try {
            Env::load($path);
            self::assertSame('hello', Env::get('FOO_BAR'));
            self::assertSame('say "hi"', Env::get('QUOTED'));
        } finally {
            unlink($path);
            putenv('FOO_BAR');
            putenv('EMPTY');
            putenv('QUOTED');
            unset($_ENV['FOO_BAR'], $_ENV['EMPTY'], $_ENV['QUOTED'], $_SERVER['FOO_BAR'], $_SERVER['EMPTY'], $_SERVER['QUOTED']);
        }
    }

    public function testGetReturnsDefaultWhenUnset(): void
    {
        $key = 'VORTEX_TEST_NO_SUCH_' . bin2hex(random_bytes(6));

        self::assertSame('d', Env::get($key, 'd'));
    }
}
