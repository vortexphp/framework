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

    public function testResolveAppliesTransformResponse(): void
    {
        $r = new class (['id' => 1]) extends JsonResource {
            public function toArray(): array
            {
                return is_array($this->resource) ? $this->resource : [];
            }

            protected function transformResponse(array $data): array
            {
                $data['meta'] = 'x';

                return $data;
            }
        };

        self::assertSame(['id' => 1, 'meta' => 'x'], $r->resolve());
        $wrapped = $r->toResponse();
        self::assertSame('{"ok":true,"data":{"id":1,"meta":"x"}}', $wrapped->body());

        $collected = JsonResource::collect([['id' => 2]], $r::class);
        self::assertSame([['id' => 2, 'meta' => 'x']], $collected);
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

    public function testApiOkValidatedPassesAndMismatchIs500(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer']],
            'required' => ['id'],
        ];
        $ok = Response::apiOkValidated(['id' => 1], $schema);
        self::assertSame(200, $ok->httpStatus());
        self::assertSame('{"ok":true,"data":{"id":1}}', $ok->body());

        $bad = Response::apiOkValidated(['id' => 'nope'], $schema);
        self::assertSame(500, $bad->httpStatus());
        $payload = json_decode($bad->body(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($payload['ok']);
        self::assertSame('response_schema_mismatch', $payload['error']);
        self::assertArrayHasKey('id', $payload['errors']);
    }

    public function testJsonValidatedMismatchIs500(): void
    {
        $schema = ['type' => 'object', 'properties' => ['a' => ['type' => 'string']], 'required' => ['a']];
        $bad = Response::jsonValidated([], $schema);
        self::assertSame(500, $bad->httpStatus());
    }

    public function testToValidatedResponsePasses(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer']],
            'required' => ['id'],
        ];
        $r = (new DemoResource(['id' => 9]))->toValidatedResponse($schema);
        self::assertSame('{"ok":true,"data":{"id":9}}', $r->body());
    }

    public function testToValidatedResponseMismatch(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'string']],
            'required' => ['id'],
        ];
        $r = (new DemoResource(['id' => 9]))->toValidatedResponse($schema);
        self::assertSame(500, $r->httpStatus());
        $p = json_decode($r->body(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('response_schema_mismatch', $p['error']);
    }

    public function testCollectionValidatedResponse(): void
    {
        $schema = [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => ['n' => ['type' => 'string']],
                'required' => ['n'],
            ],
        ];
        $r = JsonResource::collectionValidatedResponse(
            [['n' => 'a'], ['n' => 'b']],
            DemoResource::class,
            $schema,
        );
        self::assertSame('{"ok":true,"data":[{"n":"a"},{"n":"b"}]}', $r->body());

        $bad = JsonResource::collectionValidatedResponse(
            [['n' => 1]],
            DemoResource::class,
            $schema,
        );
        self::assertSame(500, $bad->httpStatus());
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
