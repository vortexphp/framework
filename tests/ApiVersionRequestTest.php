<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Http\Request;

final class ApiVersionRequestTest extends TestCase
{
    public function testSplitVersionedPath(): void
    {
        self::assertSame(['1', '/users'], Request::splitVersionedPath('/v1/users'));
        self::assertSame(['2', '/'], Request::splitVersionedPath('/v2'));
        self::assertSame(['2', '/'], Request::splitVersionedPath('/V2/'));
        self::assertSame([null, '/users'], Request::splitVersionedPath('/users'));
    }

    public function testResolvedApiVersionPrefersHeaders(): void
    {
        $pathOnly = Request::make('GET', '/v2/items', [], [], [], []);
        self::assertSame('2', $pathOnly->resolvedApiVersion());

        $headerWins = Request::make('GET', '/v9/items', [], [], ['Accept-Version' => '1'], []);
        self::assertSame('1', $headerWins->resolvedApiVersion());

        $xApi = Request::make('GET', '/', [], [], ['X-Api-Version' => 'v3'], []);
        self::assertSame('v3', $xApi->resolvedApiVersion());
    }

    public function testMatchesApiVersion(): void
    {
        $req = Request::make('GET', '/v1/a', [], [], [], []);
        self::assertTrue($req->matchesApiVersion('1'));
        self::assertTrue($req->matchesApiVersion('v1'));
        self::assertFalse($req->matchesApiVersion('2'));

        $noVer = Request::make('GET', '/a', [], [], [], []);
        self::assertFalse($noVer->matchesApiVersion('1'));
    }

    public function testWithPath(): void
    {
        $a = Request::make('GET', '/v1/x', ['q' => '1'], [], ['Accept' => 'application/json'], []);
        $b = $a->withPath('/x');
        self::assertSame('/x', $b->path);
        self::assertSame(['q' => '1'], $b->query);
        self::assertSame('/v1/x', $a->path);
        self::assertSame('/x', $b->server['REQUEST_URI'] ?? null);
    }
}
