# Testing helpers

## KernelBrowser

Boots your app base path with **`Application::boot()`**, registers a default **`ErrorRenderer`** only when the container has no binding yet, then dispatches synthetic **`Request`** values through **`Kernel`** (in-process, no sockets).

```php
<?php

use Vortex\Testing\KernelBrowser;

$browser = KernelBrowser::boot(__DIR__ . '/../../');
$response = $browser->get('/health');
$response = $browser->postJson('/api/widgets', ['name' => 'A']);
$data = KernelBrowser::decodeJson($response);

KernelBrowser::resetRequestContext(); // e.g. in tearDown()
```

Use **`KernelBrowser::request()`** for full control over query, body, headers, server, files, and cookies.

**`TrustProxies`** is not applied (that runs only in **`Kernel::send()`** before capture). For unit-style HTTP tests, prefer **`Request::make()`**-based helpers above.
