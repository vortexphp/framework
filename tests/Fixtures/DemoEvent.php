<?php

declare(strict_types=1);

namespace Vortex\Tests\Fixtures;

final class DemoEvent
{
    public function __construct(
        public string $payload = '',
    ) {
    }
}
