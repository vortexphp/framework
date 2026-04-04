# Support Module

Utility helpers and runtime support classes.

## Benchmark example

```php
<?php

use Vortex\Support\Benchmark;

Benchmark::start('request');
// ... work ...
$elapsedMs = Benchmark::elapsedMs('request');

$measured = Benchmark::measure(static function (): int {
    // expensive operation
    return 123;
});
// ['result' => 123, 'elapsed_ms' => ...]
```

## Env example

```php
<?php

use Vortex\Support\Env;

Env::load(__DIR__ . '/.env');

$appEnv = Env::get('APP_ENV', 'production');
$debug = Env::get('APP_DEBUG', '0');
```

## Log example

```php
<?php

use Vortex\Support\Log;

Log::setBasePath(__DIR__);
Log::info('User logged in', ['user_id' => 7]);

try {
    throw new RuntimeException('Something failed');
} catch (Throwable $e) {
    Log::exception($e);
}
```

## AppPaths example

```php
<?php

use Vortex\Support\AppPaths;

$paths = AppPaths::forBase(__DIR__);
$migrationsDir = $paths->migrationsDirectory(__DIR__);
// default: __DIR__ . '/db/migrations'
// override with config/paths.php => ['migrations' => 'custom/path']
```

## PathHelp + JsonHelp examples

```php
<?php

use Vortex\Support\JsonHelp;
use Vortex\Support\PathHelp;

$full = PathHelp::join('/var/www', 'storage', 'logs', 'app.log');
$inside = PathHelp::isBelowBase('/var/www/public', '/var/www/public/uploads');

$json = JsonHelp::encode(['ok' => true]);
$data = JsonHelp::tryDecodeArray($json); // ['ok' => true]
```

## Other helpers

- `StringHelp`, `ArrayHelp`, `UrlHelp`, `HtmlHelp`, `DateHelp`, `NumberHelp`, `CollectionHelp`.
