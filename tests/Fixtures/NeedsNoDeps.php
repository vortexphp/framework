<?php

declare(strict_types=1);

namespace Vortex\Tests\Fixtures;

final class NeedsNoDeps
{
    public function __construct(
        public NoDeps $inner,
    ) {
    }
}
