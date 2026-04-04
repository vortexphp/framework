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
$modelsDir = $paths->modelsDirectory(__DIR__);
// defaults: db/migrations, app/Models — override with config/paths.php => ['migrations' => '…', 'models' => '…']
```

## PathHelp + JsonHelp examples

```php
<?php

use Vortex\Support\JsonHelp;
use Vortex\Support\JsonShape;
use Vortex\Support\PathHelp;

$full = PathHelp::join('/var/www', 'storage', 'logs', 'app.log');
$inside = PathHelp::isBelowBase('/var/www/public', '/var/www/public/uploads');

$json = JsonHelp::encode(['ok' => true]);
$data = JsonHelp::tryDecodeArray($json); // ['ok' => true]

$result = JsonShape::validate($data, ['ok' => 'bool']);
$user = JsonShape::validate(
    ['user' => ['name' => 'Ada']],
    ['user' => JsonShape::object(['name' => 'string'])],
);
$rows = JsonShape::validate(
    ['rows' => [['id' => 1]]],
    ['rows' => JsonShape::listOfObjects(['id' => 'int'])],
);
$tags = JsonShape::validate(
    ['tags' => ['a', 'b']],
    ['tags' => JsonShape::listOfPrimitive('string')],
);
```

## Other helpers

- `StringHelp`, `ArrayHelp`, `UrlHelp`, `HtmlHelp`, `DateHelp`, `NumberHelp`, `CollectionHelp`.
- `JsonShape` — flat type checks for decoded JSON bodies (see Http module README); not JSON Schema.
