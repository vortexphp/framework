<?php

declare(strict_types=1);

namespace Vortex\Tests\Fixtures;

final class UnionLeafCtor
{
    public function __construct(
        public NoDeps|AltLeaf $leaf,
    ) {
    }
}
