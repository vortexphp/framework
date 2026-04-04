# Files Module

Storage facade and disk drivers.

## Example

```php
<?php

use Vortex\Files\Storage;

Storage::put('logs/app.log', "Booted\n");
Storage::append('logs/app.log', "Request done\n");

if (Storage::exists('logs/app.log')) {
    $log = Storage::get('logs/app.log');
}

Storage::delete('logs/app.log');
```

## Notes

- `Storage::disk('name')` selects a configured disk from `config/storage.php`.
- `Storage::storeUpload(...)` stores validated public uploads via a `local_public` disk.
