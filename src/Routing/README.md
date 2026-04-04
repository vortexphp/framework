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

## Generate a URL

```php
$path = route('users.show', ['id' => 7]); // /users/7
```
