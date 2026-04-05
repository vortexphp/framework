# View Module

Twig rendering through `Factory` and `View` facade.

## Example

```php
<?php

use Vortex\View\View;

View::share('appName', 'Demo App');

$html = View::render('pages.home', ['title' => 'Dashboard']);
$response = View::html('pages.home', ['title' => 'Dashboard']);
```

## Extending Twig

`Factory` supports runtime registration:

- `addExtension(...)`
- `addFilter(...)`
- `addFunction(...)`
- `addTemplatePath($absolutePath)` — extra filesystem roots for Twig templates (application views path stays first). Use from a `Package::boot()` hook **before** the first render.
