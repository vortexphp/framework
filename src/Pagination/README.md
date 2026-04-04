# Pagination Module

`Paginator` is returned by `QueryBuilder::paginate()`. For JSON APIs, `QueryBuilder::cursorPaginate()`
returns `CursorPaginator` (opaque `next_cursor`, `has_more`).

## Offset pagination (Twig)

```php
<?php

$paginator = Post::query()->where('status', 'published')->paginate(page: 2, perPage: 15);
$paginator = $paginator->withBasePath(route('posts.index'));

$items = $paginator->items;
$next = $paginator->urlForPage($paginator->page + 1);
```

## Cursor pagination (JSON)

```php
<?php

use Vortex\Http\Response;

$cursor = $request->query('cursor'); // optional
$page = Post::query()->where('status', 'published')->cursorPaginate($cursor, 15, 'id', 'ASC');

return Response::apiOk($page->toApiData(static fn (Post $p) => $p->toArray()));
```

- Cursors are URL-safe base64 JSON objects; the paginated column must appear as a key (e.g. `{"id": 42}`).
- Use **`ASC`** or **`DESC`** consistently with your API contract; **`DESC`** uses `WHERE col < ?`.

## Notes

- `Paginator` public fields are Twig-friendly: `items`, `total`, `page`, `per_page`, `last_page`.
- `Paginator` helpers: `hasPages()`, `onFirstPage()`, `onLastPage()`.
