# Database Module

Connection facade, active-record style models, query builder, and schema migrations.

## DB facade example

```php
<?php

use Vortex\Database\DB;

$users = DB::select('SELECT * FROM users WHERE active = ?', [1]);
$first = DB::selectOne('SELECT * FROM users WHERE id = ?', [7]);
```

## Model + query builder example

```php
<?php

use Vortex\Database\Model;

final class Post extends Model
{
    protected static array $fillable = ['title', 'status'];
}

$published = Post::query()
    ->where('status', 'published')
    ->orderBy('id', 'DESC')
    ->limit(20)
    ->get();
```

## Migrations

- Migration files return classes extending `Schema\Migration`.
- Use static schema methods (`Schema::create`, `Schema::table`, `Schema::dropIfExists`).
