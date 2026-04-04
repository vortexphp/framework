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
- Built-in drivers include file-backed and null stores.
