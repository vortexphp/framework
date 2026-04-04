# Cache Module

Cache drivers and cache resolution live here.

## Example

```php
<?php

use Vortex\Cache\Cache;

Cache::set('users.count', 42, 60);
$count = Cache::get('users.count', 0);

$profile = Cache::remember('user:7', 300, static function (): array {
    return ['id' => 7, 'name' => 'Ana'];
});

Cache::forget('users.count');

// Only set if the key is absent (NX); returns whether this call stored the value.
$claimed = Cache::add('schedule:job:7', 1, 600);
```

## PSR-16

**`Psr\SimpleCache\CacheInterface`** is registered in the container as a **`Psr16Cache`** adapter around the default **`Vortex\Contracts\Cache`** store. Inject **`Psr\SimpleCache\CacheInterface`** or construct **`new Psr16Cache(Cache::store())`** for libraries that expect PSR-16. Invalid keys (including empty strings) throw **`Vortex\Cache\SimpleCacheInvalidArgumentException`**.

## Notes

- `Cache::store('name')` returns a named store from `config/cache.php`.
- Built-in drivers: `file`, `null`, **`redis`** (ext-redis / phpredis), and **`memcached`** (ext-memcached). Use the multi-store config shape:

```php
<?php

declare(strict_types=1);

return [
    'default' => 'redis',
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => __DIR__ . '/../storage/cache/data',
            'prefix' => 'app:',
        ],
        'redis' => [
            'driver' => 'redis',
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => null,
            'database' => 0,
            'prefix' => 'app:',
            'timeout' => 0.0,
            'persistent' => false,
        ],
        'memcached' => [
            'driver' => 'memcached',
            'host' => '127.0.0.1',
            'port' => 11211,
            'prefix' => 'app:',
            'persistent_id' => '',
        ],
    ],
];
```

Legacy single-`driver` `cache.php` files support only `file` and `null`; add **`stores`** for Redis or Memcached.

Redis connections use **`PhpRedisConnect::connect()`** (shared with the queue Redis driver). Memcached pools use **`PhpMemcachedConnect::connect()`**; optional **`servers`** (`[[host, port, weight], ...]`) instead of `host`/`port`, optional **`sasl_user`** / **`sasl_password`**.

For **`memcached`**, **`clear()`** increments an internal generation so existing prefixed keys are abandoned (they age out via LRU); it does not call `flushAll`.
