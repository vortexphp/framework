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

## Global scopes

Register named constraints applied to every **`Model::query()`** (and therefore **`all()`** / **`find()`**):

```php
<?php

use Vortex\Database\Model;
use Vortex\Database\QueryBuilder;

final class Article extends Model
{
    // boot() in your app: Article::addGlobalScope('active', static function (QueryBuilder $q): void {
    //     $q->where('status', 'published');
    // });
}
```

Scopes run in **`applyGlobalScope()`**; **`where*`** calls inside the callback are tagged so you can remove them with **`withoutGlobalScope('active')`** or **`withoutGlobalScopes()`** on the builder. Constraints added outside a scope callback are not removed.

## Eager loading (`with`)

Declare **`protected static function eagerRelations(): array`** on the parent model. Keys are the same as your public relation method names; values describe **`belongsTo`**, **`hasMany`**, or **`belongsToMany`** so **`QueryBuilder::with()`** can load related rows in batches (otherwise **`with()`** falls back to calling each relation method per model).

Shapes:

- **`['belongsTo', Related::class, 'foreign_id', 'owner_id?']`**
- **`['hasMany', Related::class, 'foreign_id', 'local_id?']`**
- **`['hasOne', Related::class, 'foreign_id', 'local_id?']`** — same FK layout as **`hasMany`**; eager load assigns a single related model (lowest **`id`** if duplicates exist).
- **`['belongsToMany', Related::class, 'pivot_table', 'foreign_pivot_key', 'related_pivot_key', 'parent_key?', 'related_key?']`**

Nested relations use dot paths, for example **`->with(['author.country'])`** or **`->with('comments.author')`**. Each segment must exist on that level’s **`eagerRelations()`** map (or resolve via the per-model relation method when no map entry exists).

Use **`Vortex\Database\Relation::belongsTo()`**, **`Relation::hasMany()`**, **`Relation::hasOne()`**, and **`Relation::belongsToMany()`** to build the same arrays as the bullet shapes above (keeps keys aligned with your relation methods).

**`$model->load('author')`** or **`$model->load(['author', 'tags'])`** runs the same batched loader as **`QueryBuilder::with()`** on a single instance (including nested dot paths). For arbitrary collections, **`$query->with([...])->eagerLoadOnto($models)`** applies queued relation paths without executing the query.

## Migrations

- Migration files return classes extending `Schema\Migration`.
- Use static schema methods: **`Schema::create`**, **`Schema::table`**, **`Schema::dropIfExists`**, **`Schema::hasTable`** (SQLite, MySQL, PostgreSQL).
- **`Blueprint`**: **`id`**, **`foreignId`** (with **`constrained`**, **`onDelete` / `onUpdate`** actions), **`string`**, **`char`**, **`text`**, **`integer`**, **`bigInteger`**, **`smallInteger`** (MySQL **`unsigned()`** on integers), **`decimal`**, **`floatType`**, **`boolean`**, **`date`**, **`dateTime`**, **`timestamp`**, **`json`** (SQLite `TEXT`, MySQL `JSON`, PostgreSQL `JSONB`), **`timestamps`**, **`index`**, **`unique`**.
