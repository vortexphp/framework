<?php

declare(strict_types=1);

namespace Vortex\Tests\Fixtures;

final class InvokableSum
{
    public function __construct(
        private NoDeps $n,
    ) {
    }

    public function __invoke(int $a, int $b = 1): int
    {
        return $a + $b;
    }
}
