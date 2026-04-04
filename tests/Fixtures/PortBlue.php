<?php

declare(strict_types=1);

namespace Vortex\Tests\Fixtures;

final class PortBlue implements Port
{
    public function mark(): string
    {
        return 'blue';
    }
}
