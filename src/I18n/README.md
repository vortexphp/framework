# I18n Module

Localized string lookup through `Translator`.

## Example

```php
<?php

use Vortex\I18n\Translator;

Translator::setLocale('bg');

$title = Translator::get('auth.login.title');
$welcome = Translator::get('messages.welcome', ['name' => 'Ana']);
```

## Notes

- Translation files are loaded from `lang/<locale>.php`.
- Missing keys fall back to fallback locale, then return the key string.
