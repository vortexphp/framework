<?php

declare(strict_types=1);

namespace Vortex\Tests\Fixtures;

final class StaticAdd
{
    public static function combine(int $x, NoDeps $n, int $y = 10): int
    {
        return $x + $y;
    }
}
