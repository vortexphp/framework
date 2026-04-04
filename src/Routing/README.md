# Routing Module

Route registration, route discovery, and URL generation.

## Example route file

`app/Routes/Web.php`:

```php
<?php

use Vortex\Http\Response;
use Vortex\Routing\Route;

Route::get('/', static fn (): Response => Response::html('Home'))->name('home');

Route::get('/users/{id}', static fn (string $id): Response => Response::json(['id' => $id]))
    ->name('users.show');
```

## Model and custom binding

On the active router (often via `Route::useRouter()` during bootstrap), register resolvers for path parameters before defining routes:

- **`$router->model('post', Post::class)`** — loads `Post` by `id` (or pass a third argument for another unique column, e.g. `'slug'`). Missing row → **404**.
- **`$router->bind('token', fn (string $value): ?array => ...)`** — custom resolution; return **`null`** for **404**.

`Route::model(...)` and `Route::bind(...)` forward to the same router instance.

## Generate a URL

```php
$path = route('users.show', ['id' => 7]); // /users/7
```
