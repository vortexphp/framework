<?php

declare(strict_types=1);

namespace Vortex\Cache;

use InvalidArgumentException;
use Vortex\Config\Repository;
use Vortex\Contracts\Cache;

final class CacheFactory
{
    public static function make(string $basePath): Cache
    {
        $driver = strtolower(trim((string) Repository::get('cache.driver', 'file')));
        if ($driver === 'null') {
            return new NullCache();
        }
        if ($driver !== 'file') {
            throw new InvalidArgumentException("Unsupported cache driver [{$driver}]. Use file or null.");
        }

        return new FileCache(
            (string) Repository::get('cache.path', rtrim($basePath, '/') . '/storage/cache/data'),
            (string) Repository::get('cache.prefix', 'vortex:'),
        );
    }
}
