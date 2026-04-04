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
```

## Notes

- `Cache::store('name')` returns a named store from `config/cache.php`.
- Built-in drivers: `file`, `null`, and **`redis`** (requires **ext-redis** / phpredis). Use the multi-store config shape:

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
    ],
];
```

Legacy single-`driver` `cache.php` files support only `file` and `null`; add **`stores`** for Redis.

Redis connections are created with **`Vortex\Support\PhpRedisConnect::connect()`** (shared with the queue Redis driver).
