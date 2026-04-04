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

## Attribute casts

Declare **`protected static array $casts`** (`attribute => type`). On load (`fromRow` / `find` / query results), values are converted for use in PHP; on **`save()`** and **`updateRecord()`**, they are converted for the database.

Supported types: **`int`**, **`float`**, **`bool`**, **`string`**, **`json`** / **`array`** (JSON text in SQL), **`datetime`** (`DateTimeImmutable` in memory; `Y-m-d H:i:s` when stored). Unknown cast names throw **`InvalidArgumentException`**.

## Soft deletes

Set **`protected static bool $softDeletes = true`** and a **`protected static string $deletedAtColumn`** (default **`deleted_at`**, SQL `NULL` when active). **`find()`**, **`all()`**, and **`QueryBuilder`** queries exclude rows where that column is non-null. **`delete()`** on the model sets the timestamp; **`forceDelete()`** removes the row. **`restore()`** clears **`deleted_at`**.

**`QueryBuilder::withTrashed()`** drops that filter; **`onlyTrashed()`** keeps only soft-deleted rows. Builder **`delete()`** performs a mass soft delete; **`onlyTrashed()->delete()`** runs a hard **`DELETE`**. **`Model::deleteId()`** always hard-deletes.

## Migrations

- Migration files return classes extending `Schema\Migration`.
- Use static schema methods (`Schema::create`, `Schema::table`, `Schema::dropIfExists`).
