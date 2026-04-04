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

## JSON API envelope

- **`Response::apiOk($data)`** — `{ "ok": true, "data": ... }`.
- **`Response::apiError($status, $errorCode, $message, $extra = [])`** — `{ "ok": false, "error", "message", ... }` (always JSON).
- **`Response::validationFailed($result)`** — **`422`** (by default) with **`error: validation_failed`** and **`errors`** (field → message from **`ValidationResult`**).
- **`JsonResource`** — implement `toArray()`; **`toResponse()`** / **`collectionResponse()`** build **`apiOk`**-wrapped responses.

For HTML vs JSON negotiation, **`Response::notFound()`**, **`forbidden()`**, **`unauthorized()`**, and **`error()`** include **`ok`**, **`error`** (machine code), and **`message`** when **`Request::wantsJson()`** is true.

## Notes

- `Request::wantsJson()` is true for `Accept: application/json` and `X-Requested-With: XMLHttpRequest`.
- `Response::notFound()`, `forbidden()`, `unauthorized()`, and `error()` auto-select HTML/JSON output.
