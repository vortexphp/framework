<?php

declare(strict_types=1);

namespace Vortex\Cache;

use Vortex\Contracts\Cache as CacheContract;

/** Resolves the default {@see CacheContract} from {@see CacheManager}. */
final class CacheFactory
{
    public static function make(string $basePath): CacheContract
    {
        return CacheManager::fromRepository($basePath)->store();
    }
}
