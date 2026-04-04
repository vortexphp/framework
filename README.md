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
- `config/paths.php` (optional) — return `['migrations' => '…']` relative to the project root; default is `db/migrations`
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

## Core usage

### Router

- Define HTTP routes in `app/Routes/*.php`.
- Name routes with `->name('...')` and generate URLs with `route('name', $params)`.

```php
<?php

use Vortex\Http\Response;
use Vortex\Routing\Route;

Route::get('/', static fn (): Response => Response::html('Home'))->name('home');

Route::get('/posts/{id}', static function (string $id): Response {
    return Response::json(['id' => $id]);
})->name('posts.show');

Route::post('/posts', static fn (): Response => Response::redirect(route('home')));
```

### Authentication

- Use `Vortex\Auth\Auth` for session login/logout (`$remember` on login sets a signed cookie; requires `APP_KEY`).
- `Vortex\Auth\Gate` for abilities and model policies; `Vortex\Auth\AuthorizationException` when using `Gate::authorize()` (handled as 403).
- Middleware: `Authenticate`, `RememberFromCookie`, and subclass `AuthorizeAbility` for route protection; list class names under `app.middleware`.
- `Vortex\Auth\PasswordResetBroker` for opaque reset tokens stored in SQL (you send mail and define routes).
- In Twig: `auth_check()`, `auth_id()`, `auth_user()`, `gate_allows(...)`.

See `src/Auth/README.md` for config keys, middleware order, and a minimal `password_reset_tokens` schema.

### Database model

- Extend `Vortex\Database\Model` and declare `$fillable` for mass assignment.
- Override `protected static ?string $table` when default pluralized snake_case naming is not desired.
- Use `query()` for filters, joins, grouping, eager loading, pagination, and bulk updates/deletes.

```php
<?php

use Vortex\Database\Model;

final class Post extends Model
{
    protected static ?string $table = 'posts';
    protected static array $fillable = ['user_id', 'title', 'body'];

    public function user(): ?User
    {
        /** @var User|null */
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return list<Comment> */
    public function comments(): array
    {
        /** @var list<Comment> */
        return $this->hasMany(Comment::class, 'post_id');
    }
}

$posts = Post::query()
    ->where('status', 'published')
    ->with(['user', 'comments'])
    ->orderBy('id', 'DESC')
    ->limit(10)
    ->get();
```

### Migrations

- Migration classes live in `db/migrations` by default (or custom `config/paths.php` `migrations` path).
- Each migration file must return a class extending `Vortex\Database\Schema\Migration`.
- Migration ID is the filename (without `.php`).

```php
<?php

use Vortex\Database\Schema\Migration;
use Vortex\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('posts', static function ($table): void {
            $table->id();
            $table->string('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
```

### Validation

- `Validator::make()` accepts pipe strings and fluent `Vortex\Validation\Rule` objects.

```php
$result = Validator::make($data, [
    'email' => Rule::required()->email()->max(255),
    'password' => Rule::required()->min(8)->confirmed(),
]);
```

### Responses and request JSON detection

- `Request::wantsJson()` is true for `Accept: application/json` and `X-Requested-With: XMLHttpRequest`.
- `Response` includes helpers for common error responses and flash data:
  - `Response::notFound()`, `forbidden()`, `unauthorized()`, `error()`
  - `->with()`, `->withMany()`, `->withErrors()`, `->withInput()`

### Scheduler

- Add `config/schedule.php` with `tasks` (`cron` + handler `class`) and run **`php vortex schedule:run`** from cron each minute (see `src/Schedule/README.md`). Optional `app.timezone` for due-time evaluation.

### Queue

- Implement `Vortex\Queue\Contracts\Job`, push with `Vortex\Queue\Queue::push()` after boot. Default driver uses a SQL `jobs` table; set `queue.driver` to `redis` and `queue.redis` for a Redis-backed `QueueDriver` (see `src/Queue/README.md`).
- Optional `failed_jobs` table and `queue:failed` / `queue:retry` for dead-letter handling (see `src/Queue/README.md`).
- Run `php vortex queue:work` (add `once` to process one job and exit).

Use the `Vortex\` namespace for framework types. See the test suite under `tests/` for concrete usage patterns.

**Testing HTTP in-process:** `Kernel::handle(Request::make('GET', '/path'))` returns a `Response` without sending output; register `ErrorRenderer` on the container when using the full error stack (see `tests/KernelHandleTest.php`).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT.
