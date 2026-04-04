# Crypto Module

Password hashing and keyed message authentication.

## Example

```php
<?php

use Vortex\Crypto\Crypt;
use Vortex\Crypto\Password;

$hash = Password::hash('secret123');
$ok = Password::verify('secret123', $hash);

$mac = Crypt::hash('order:123');
$valid = Crypt::verify('order:123', $mac);
```

## Notes

- Use `Password` for user credentials.
- Use `Crypt` for HMAC signatures with `APP_KEY`.
