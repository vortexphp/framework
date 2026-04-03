<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Pagination\Paginator;

final class PaginatorTest extends TestCase
{
    public function testLastPageMinimumOne(): void
    {
        $p = new Paginator([], 0, 1, 15, 1);

        self::assertSame(1, $p->last_page);
        self::assertFalse($p->hasPages());
        self::assertTrue($p->onFirstPage());
        self::assertTrue($p->onLastPage());
    }

    public function testUrlForPageClampsAndMergesQuery(): void
    {
        $p = (new Paginator([1, 2], 40, 2, 15, 3))->withBasePath('/items');

        self::assertSame('/items?page=1', $p->urlForPage(0));
        self::assertSame('/items?page=3', $p->urlForPage(99));
        self::assertSame('/items?foo=1&page=2', (new Paginator([], 30, 1, 15, 2))->withBasePath('/items?foo=1')->urlForPage(2));
    }

    public function testHasPagesWhenMultiple(): void
    {
        $p = new Paginator(['a'], 20, 1, 15, 2);

        self::assertTrue($p->hasPages());
        self::assertFalse($p->onLastPage());
    }
}
