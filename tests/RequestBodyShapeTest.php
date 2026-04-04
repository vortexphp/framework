<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Http\Request;
use Vortex\Http\Response;

final class RequestBodyShapeTest extends TestCase
{
    public function testBodyShapeResponseReturnsNullWhenValid(): void
    {
        $req = Request::make('POST', '/api', [], ['name' => 'Ada', 'age' => 30], [], ['Accept' => 'application/json']);
        self::assertNull($req->bodyShapeResponse([
            'name' => 'string',
            'age' => 'int',
        ]));
    }

    public function testBodyShapeResponseReturnsValidationJson(): void
    {
        $req = Request::make('POST', '/api', [], ['name' => 99], [], ['Accept' => 'application/json']);
        $res = $req->bodyShapeResponse(['name' => 'string']);
        self::assertInstanceOf(Response::class, $res);
        self::assertSame(422, $res->httpStatus());
        self::assertStringContainsString('validation_failed', $res->body());
        self::assertStringContainsString('name', $res->body());
    }
}
