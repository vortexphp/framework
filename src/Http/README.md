# Http Module

Request/response/session/kernel middleware flow.

## In-process request example (tests)

```php
<?php

use Vortex\Http\Kernel;
use Vortex\Http\Request;

$request = Request::make('GET', '/health', [], [], ['Accept' => 'application/json']);
$response = $container->make(Kernel::class)->handle($request);

$status = $response->httpStatus();
$body = $response->body();
```

## Response + flash example

```php
<?php

use Vortex\Http\Response;

return Response::redirect('/login')
    ->withErrors(['email' => 'Invalid credentials'])
    ->withInput();
```

## Notes

- `Request::wantsJson()` is true for `Accept: application/json` and `X-Requested-With: XMLHttpRequest`.
- `Response::notFound()`, `forbidden()`, `unauthorized()`, and `error()` auto-select HTML/JSON output.
