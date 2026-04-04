<?php

declare(strict_types=1);

namespace Vortex\Cache;

use Psr\SimpleCache\InvalidArgumentException as Psr16InvalidArgument;

final class SimpleCacheInvalidArgumentException extends \InvalidArgumentException implements Psr16InvalidArgument
{
}
