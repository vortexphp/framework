<?php

declare(strict_types=1);

namespace Vortex\Pagination;

use Vortex\Support\UrlHelp;

/**
 * Result of {@see \Vortex\Database\QueryBuilder::paginate()}. Exposes snake_case public properties
 * for Twig (`pagination.page`, `pagination.last_page`, …). Use {@see withBasePath()} for
 * {@see urlForPage()} link generation.
 */
final class Paginator
{
    /**
     * @param list<mixed> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $per_page,
        public readonly int $last_page,
        private readonly string $basePath = '',
        private readonly string $pageParameter = 'page',
    ) {
    }

    /**
     * Clone with a path used by {@see urlForPage()} (e.g. `/blog/manage` or `route('blog.manage.index')`).
     */
    public function withBasePath(string $path, string $pageParameter = 'page'): self
    {
        return new self(
            $this->items,
            $this->total,
            $this->page,
            $this->per_page,
            $this->last_page,
            $path,
            $pageParameter,
        );
    }

    public function urlForPage(int $page): string
    {
        $p = max(1, min($page, $this->last_page));

        return UrlHelp::withQuery($this->basePath, [$this->pageParameter => $p]);
    }

    public function hasPages(): bool
    {
        return $this->last_page > 1;
    }

    public function onFirstPage(): bool
    {
        return $this->page <= 1;
    }

    public function onLastPage(): bool
    {
        return $this->page >= $this->last_page;
    }
}
