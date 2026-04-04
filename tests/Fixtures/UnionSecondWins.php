<?php

declare(strict_types=1);

namespace Vortex\Tests\Fixtures;

final class UnionSecondWins
{
    public function __construct(
        public OrphanInterface|AltLeaf $pick,
    ) {
    }
}
