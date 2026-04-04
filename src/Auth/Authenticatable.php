<?php

declare(strict_types=1);

namespace Vortex\Auth;

interface Authenticatable
{
    public function authIdentifier(): int;
}
