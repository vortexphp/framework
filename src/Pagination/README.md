# Pagination Module

`Paginator` is returned by `QueryBuilder::paginate()`.

## Example

```php
<?php

$paginator = Post::query()->where('status', 'published')->paginate(page: 2, perPage: 15);
$paginator = $paginator->withBasePath(route('posts.index'));

$items = $paginator->items;
$next = $paginator->urlForPage($paginator->page + 1);
```

## Notes

- Public fields are Twig-friendly: `items`, `total`, `page`, `per_page`, `last_page`.
- Helper methods: `hasPages()`, `onFirstPage()`, `onLastPage()`.
