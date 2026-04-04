<?php

declare(strict_types=1);

namespace Vortex\Tests\Fixtures;

final class PortRed implements Port
{
    public function mark(): string
    {
        return 'red';
    }
}
