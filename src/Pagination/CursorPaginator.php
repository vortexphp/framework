<?php

declare(strict_types=1);

namespace Vortex\Pagination;

/**
 * Result of {@see \Vortex\Database\QueryBuilder::cursorPaginate()}. Use {@see toApiData()} with
 * {@see \Vortex\Http\Response::apiOk()} for JSON collections.
 *
 * @phpstan-type ApiData array{items: list<mixed>, next_cursor: ?string, has_more: bool, per_page: int}
 */
final class CursorPaginator
{
    /**
     * @param list<mixed> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly ?string $next_cursor,
        public readonly bool $has_more,
        public readonly int $per_page,
    ) {
    }

    /**
     * @param (callable(mixed): mixed)|null $map
     * @return ApiData
     */
    public function toApiData(?callable $map = null): array
    {
        $items = $map !== null ? array_map($map, $this->items) : $this->items;

        return [
            'items' => $items,
            'next_cursor' => $this->next_cursor,
            'has_more' => $this->has_more,
            'per_page' => $this->per_page,
        ];
    }
}
