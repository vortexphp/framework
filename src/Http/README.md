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
- **`JsonResource`** — implement **`toArray()`**; optional **`pushResponseTransform(callable)`** (constructor) or **`withResponseTransforms(...)`** (clone) for multi-step **`(array): array`** maps in order, then optional **`transformResponse()`**. **`resolve()`** runs that chain. **`toResponse()`** / **`collectionResponse()`** use **`resolve()`** and wrap with **`apiOk`**. **`toValidatedResponse($schema)`** and **`collectionValidatedResponse(...)`** validate **`resolve()`** output; mismatch → **500** **`response_schema_mismatch`** (same as **`Response::apiOkValidated`** / **`jsonValidated`**).

For HTML vs JSON negotiation, **`Response::notFound()`**, **`forbidden()`**, **`unauthorized()`**, and **`error()`** include **`ok`**, **`error`** (machine code), and **`message`** when **`Request::wantsJson()`** is true.

## Validate request input (API)

On a **`Request`** instance, **`validationResponse($rules, $messages = [], $attributes = [])`** runs **`Validator::make`** on query + body (body wins on duplicate keys) and returns **`Response::validationFailed()`** or **`null`**. **`bodyValidationResponse(...)`** uses only **`$request->body`**. With a stack request, call **`Request::current()->validationResponse(...)`**.

## JSON body shape (decoded array)

**`JsonShape::validate($body, $shape)`** in **`Vortex\Support`** checks required keys and primitive/list/object **`types`** for API JSON (not a full JSON Schema). Use **`JsonShape::object([...], optional: bool)`** for nested objects (dot-path errors, e.g. **`user.email`**). Use **`JsonShape::listOf(JsonShape::object([...]))`** or **`JsonShape::listOfObjects([...])`** for homogeneous object lists (errors like **`items.0.qty`**), and **`JsonShape::listOfPrimitive('int')`** (or **`'?int'`** for nullable elements) for homogeneous primitive lists (errors like **`ids.1`**). **`$request->bodyShapeResponse($shape)`** returns **`Response::validationFailed()`** or **`null`**.

## Server-Sent Events (SSE)

**`Response::serverSentEvents(callable $writer)`** sets **`text/event-stream`** headers; **`$writer`** receives **`SseEmitter`** (`message`, `json`, `comment`). Call **`send()`** on the response to run the writer (after headers). Pair with **`Vortex\Broadcasting\Contracts\Broadcaster`** in the same app if listeners should fan out to SSE routes (subscribe in **`SyncBroadcaster::listen`** and write events with **`SseEmitter`**).

## JSON Schema (decoded array)

**`JsonSchemaValidator::validateArray($body, $schema)`** validates against a JSON Schema (assoc array or decoded object). **`JsonSchemaValidator::validateDecoded($data, $schema)`** accepts any JSON-encodable value (e.g. lists from **`JsonResource::collect()`**). **`$request->bodyJsonSchemaResponse($schema)`** returns **`422`** **`validation_failed`** when invalid. **`Response::apiOkValidated($data, $schema)`** and **`Response::jsonValidated($data, $schema)`** return **500** **`response_schema_mismatch`** when the payload does not match (server contract). Violation keys are normalized to dot paths (**`items.0.id`**). Prefer **`JsonShape`** when a small structural check is enough; use JSON Schema when you want **`$ref`**, **`oneOf`**, **`format`**, and spec‑driven contracts.

## API versioning helpers

- **`Request::splitVersionedPath($path)`** — detects **`/v{n}/...`** (case-insensitive **`v`**) and returns `[ version, innerPath ]`.
- **`$request->apiVersionFromHeaders()`** — **`Accept-Version`** or **`X-Api-Version`**.
- **`$request->resolvedApiVersion()`** — header value if present, else numeric segment from the path.
- **`$request->matchesApiVersion('1')`** — compares after optional leading **`v`**.
- **`$request->withPath('/inner')`** — copy with a new path (e.g. strip version prefix before routing).

## Notes

- `Request::wantsJson()` is true for `Accept: application/json` and `X-Requested-With: XMLHttpRequest`.
- `Response::notFound()`, `forbidden()`, `unauthorized()`, and `error()` auto-select HTML/JSON output.
