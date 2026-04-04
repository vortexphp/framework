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

## Validate request input (API)

On a **`Request`** instance, **`validationResponse($rules, $messages = [], $attributes = [])`** runs **`Validator::make`** on query + body (body wins on duplicate keys) and returns **`Response::validationFailed()`** or **`null`**. **`bodyValidationResponse(...)`** uses only **`$request->body`**. With a stack request, call **`Request::current()->validationResponse(...)`**.

## JSON body shape (decoded array)

**`JsonShape::validate($body, $shape)`** in **`Vortex\Support`** checks required keys and primitive/list/object **`types`** for API JSON (not a full JSON Schema). Use **`JsonShape::object([...], optional: bool)`** for nested objects (dot-path errors, e.g. **`user.email`**). Use **`JsonShape::listOf(JsonShape::object([...]))`** or **`JsonShape::listOfObjects([...])`** for homogeneous object lists (errors like **`items.0.qty`**). **`$request->bodyShapeResponse($shape)`** returns **`Response::validationFailed()`** or **`null`**.

## API versioning helpers

- **`Request::splitVersionedPath($path)`** — detects **`/v{n}/...`** (case-insensitive **`v`**) and returns `[ version, innerPath ]`.
- **`$request->apiVersionFromHeaders()`** — **`Accept-Version`** or **`X-Api-Version`**.
- **`$request->resolvedApiVersion()`** — header value if present, else numeric segment from the path.
- **`$request->matchesApiVersion('1')`** — compares after optional leading **`v`**.
- **`$request->withPath('/inner')`** — copy with a new path (e.g. strip version prefix before routing).

## Notes

- `Request::wantsJson()` is true for `Accept: application/json` and `X-Requested-With: XMLHttpRequest`.
- `Response::notFound()`, `forbidden()`, `unauthorized()`, and `error()` auto-select HTML/JSON output.
