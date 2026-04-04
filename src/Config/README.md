# Config Module

Config is loaded from `config/*.php` and accessed through `Repository`.

## Example

```php
<?php

use Vortex\Config\Repository;

$debug = (bool) Repository::get('app.debug', false);
$dbDriver = (string) Repository::get('database.connections.default.driver', 'sqlite');

if (Repository::has('mail.from.address')) {
    $from = (string) Repository::get('mail.from.address');
}
```

## Notes

- Dot notation is supported (`file.key.nested`).
- `Application::boot()` initializes the repository singleton.
