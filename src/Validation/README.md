# Validation Module

Validation rules and result object.

## Example

```php
<?php

use Vortex\Validation\Rule;
use Vortex\Validation\Validator;

$result = Validator::make(
    ['email' => 'bad', 'password' => 'short'],
    [
        'email' => Rule::required()->email(),
        'password' => Rule::required()->min(8)->confirmed(),
    ],
    ['password.confirmed' => 'Password confirmation mismatch.'],
);

if ($result->failed()) {
    $errors = $result->errors();
}
```

## Notes

- Rules can be pipe strings or fluent `Rule` objects.
- Validation stops per-field on first failed rule.
