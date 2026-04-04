<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Vortex;

final class VortexTest extends TestCase
{
    public function testCommandWithoutActiveApplicationThrows(): void
    {
        $this->expectException(RuntimeException::class);
        Vortex::command(new class extends Command {
            public function description(): string
            {
                return 'x';
            }

            protected function execute(Input $input): int
            {
                return 0;
            }
        });
    }
}
