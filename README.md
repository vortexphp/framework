# Vortex

Lightweight PHP application stack: HTTP routing, Twig views, PDO database layer, mail, cache, config, console, events, validation, and i18n.

## Install

```bash
composer require vortexphp/framework
```

Requires **PHP 8.2+**, **ext-mbstring**, **ext-pdo**, and **Twig 3**. For SMTP with TLS/SSL, install **ext-openssl** (see `composer.json` suggest).

## Project layout

The framework expects a **base path** (your app root) with at least:

- `config/` — configuration read by `Vortex\Config\Repository`
- `startup/app.php` — must `return` the `Vortex\Container` instance (used by `php vortex migrate`, `migrate:down`, `db-check`)
- `app/Routes/*.php` — HTTP route files (`require`d in order; register via `Vortex\Routing\Route`; optional `->name('key')` + `route('key', $params)`)
- `app/Routes/*Console.php` — console route files (return `callable(ConsoleApplication): void`)
- `assets/views/` — Twig templates (used by `Vortex\Application::boot()`)
- `storage/cache/twig/` — optional Twig cache when `app.debug` is false

## Quick start

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Vortex\Application;

$app = Application::boot(__DIR__); // loads `.env`, registers core services; optional 2nd arg: ?callable $configure(Container, $basePath)
$app->run(); // or use Http\Kernel with global middleware from config
```

Use the `Vortex\` namespace for framework types. See the test suite under `tests/` for concrete usage patterns.

**Testing HTTP in-process:** `Kernel::handle(Request::make('GET', '/path'))` returns a `Response` without sending output; register `ErrorRenderer` on the container when using the full error stack (see `tests/KernelHandleTest.php`).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT.
