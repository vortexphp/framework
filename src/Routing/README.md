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

## Resource routes

**`Route::resource('photos', PhotoController::class)`** (or **`$router->resource(...)`**) registers REST-style **`index`**, **`store`**, **`show`**, **`update`** (PUT + PATCH), and **`destroy`**. By default it **omits** **`create`** and **`edit`** (API-style). Use **`['except' => []]`** for the full set including **`GET .../create`** and **`GET .../{id}/edit`**.

Options: **`only`**, **`except`**, **`parameter`** (placeholder name; otherwise derived from the last URI segment), **`middleware`**, **`names`** (`false`, `true`, or a string prefix such as **`api`** for **`api.photos.index`**).

Without **`create`**, a path like **`/photos/create`** is handled by **`show`** with **`id = create`**. Use numeric IDs, UUIDs, or register **`create`** if that path must be reserved.

## Generate a URL

```php
$path = route('users.show', ['id' => 7]); // /users/7
```
