<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Http\JsonResource;
use Vortex\Http\Response;

final class JsonResourceTest extends TestCase
{
    public function testToResponseWrapsWithApiOk(): void
    {
        $r = (new DemoResource(['id' => 1, 'label' => 'a']))->toResponse();

        self::assertSame(200, $r->httpStatus());
        self::assertSame('{"ok":true,"data":{"id":1,"label":"a"}}', $r->body());
    }

    public function testToResponseWithoutWrapEmitsRawObject(): void
    {
        $r = (new DemoResource(['id' => 2]))->toResponse(201, false);

        self::assertSame(201, $r->httpStatus());
        self::assertSame('{"id":2}', $r->body());
    }

    public function testCollectBuildsArrayOfArrays(): void
    {
        $rows = JsonResource::collect(
            [['x' => 1], ['x' => 2]],
            DemoResource::class,
        );

        self::assertSame([['x' => 1], ['x' => 2]], $rows);
    }

    public function testCollectionResponseWrapsList(): void
    {
        $r = JsonResource::collectionResponse(
            [['n' => 'p'], ['n' => 'q']],
            DemoResource::class,
        );

        self::assertSame('{"ok":true,"data":[{"n":"p"},{"n":"q"}]}', $r->body());
    }

    public function testApiErrorShape(): void
    {
        $r = Response::apiError(422, 'validation_failed', 'Bad', ['errors' => ['e' => 1]]);

        self::assertSame(422, $r->httpStatus());
        self::assertSame(
            '{"ok":false,"error":"validation_failed","message":"Bad","errors":{"e":1}}',
            $r->body(),
        );
    }

    public function testApiOkShape(): void
    {
        self::assertSame('{"ok":true,"data":{"k":"v"}}', Response::apiOk(['k' => 'v'])->body());
    }
}

/** @internal */
final class DemoResource extends JsonResource
{
    public function toArray(): array
    {
        return is_array($this->resource) ? $this->resource : [];
    }
}
