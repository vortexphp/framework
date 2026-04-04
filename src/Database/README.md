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

## Model observers

Register listeners on a concrete model class; implement any subset of **`saving`**, **`creating`**, **`updating`**, **`deleting`** (before the query) and **`saved`**, **`created`**, **`updated`**, **`deleted`** (after). Pass an object or a class name (constructed with `new`).

```php
<?php

use Vortex\Database\Model;

final class PostObserver
{
    public function creating(Post $post): void
    {
        $post->title = trim((string) $post->title);
    }
}

Post::observe(PostObserver::class);
```

`Model::create()`, `save()`, and `delete()` dispatch these events. Static helpers such as **`updateRecord`** / **`deleteId`** do not.

## Migrations

- Migration files return classes extending `Schema\Migration`.
- Use static schema methods (`Schema::create`, `Schema::table`, `Schema::dropIfExists`).
