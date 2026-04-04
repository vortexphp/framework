# Events Module

Synchronous in-process event dispatching.

## Example

```php
<?php

use Vortex\AppContext;
use Vortex\Events\Dispatcher;

final class UserRegistered
{
    public function __construct(public int $userId) {}
}

$dispatcher = AppContext::container()->make(Dispatcher::class);
$dispatcher->listen(UserRegistered::class, static function (UserRegistered $event): void {
    // send welcome email, enqueue side effects, etc.
});

$dispatcher->dispatch(new UserRegistered(7));
```

## Facade

- `EventBus::dispatch($event)` dispatches through the container singleton dispatcher.
