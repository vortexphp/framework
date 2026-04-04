# Source Modules

This directory contains the framework runtime code, split by concern.

## Bootstrapping example

```php
<?php

use Vortex\Application;

$app = Application::boot(__DIR__);
$app->run();
```

## Core runtime classes in `src/`

- `Application` - boots container singletons and loads routes.
- `Container` - DI container for `bind`, `singleton`, and `make`.
- `AppContext` - global container access point for facades/helpers.

## Module directories

Each top-level module has its own README with real usage snippets:

- `Cache/README.md`
- `Config/README.md`
- `Console/README.md`
- `Contracts/README.md`
- `Crypto/README.md`
- `Database/README.md`
- `Events/README.md`
- `Files/README.md`
- `Http/README.md`
- `I18n/README.md`
- `Mail/README.md`
- `Pagination/README.md`
- `Routing/README.md`
- `Support/README.md`
- `Validation/README.md`
- `View/README.md`
