# Contracts Module

Shared interfaces used by framework components and custom drivers.

## Example middleware contract

```php
<?php

use Closure;
use Vortex\Contracts\Middleware;
use Vortex\Http\Request;
use Vortex\Http\Response;

final class AdminOnly implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->path !== '/admin') {
            return Response::forbidden();
        }

        return $next($request);
    }
}
```

## Other contracts

- `Cache` - cache store API.
- `Filesystem` / `PublicFilesystem` - storage disk drivers.
- `Mailer` - message delivery.
