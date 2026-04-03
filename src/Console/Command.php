<?php

declare(strict_types=1);

namespace Vortex\Console;

interface Command
{
    public function name(): string;

    public function description(): string;

    public function run(Input $input): int;
}
