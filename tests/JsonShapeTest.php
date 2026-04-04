<?php

declare(strict_types=1);

namespace Vortex\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vortex\Support\JsonShape;

final class JsonShapeTest extends TestCase
{
    public function testRequiredAndTypes(): void
    {
        $r = JsonShape::validate(
            ['title' => 'Hi', 'count' => 3, 'ok' => true, 'items' => [1, 2]],
            [
                'title' => 'string',
                'count' => 'int',
                'ok' => 'bool',
                'items' => 'list',
            ],
        );
        self::assertFalse($r->failed());

        $bad = JsonShape::validate(['title' => 1], ['title' => 'string']);
        self::assertTrue($bad->failed());
        self::assertStringContainsString('string', (string) $bad->first('title'));
    }

    public function testOptionalAndNull(): void
    {
        $r = JsonShape::validate([], ['bio' => '?string']);
        self::assertFalse($r->failed());

        $r2 = JsonShape::validate(['bio' => null], ['bio' => '?string']);
        self::assertFalse($r2->failed());
    }

    public function testObjectVersusList(): void
    {
        self::assertFalse(JsonShape::validate(['m' => ['a' => 1]], ['m' => 'object'])->failed());
        self::assertTrue(JsonShape::validate(['m' => [1, 2]], ['m' => 'object'])->failed());
        self::assertFalse(JsonShape::validate(['m' => [1, 2]], ['m' => 'list'])->failed());
    }

    public function testUnknownTypeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JsonShape::validate(['x' => 1], ['x' => 'uuid']);
    }

    public function testNestedObjectValidatesWithDotPaths(): void
    {
        $ok = JsonShape::validate(
            ['user' => ['name' => 'Ada', 'age' => 30]],
            [
                'user' => JsonShape::object([
                    'name' => 'string',
                    'age' => 'int',
                ]),
            ],
        );
        self::assertFalse($ok->failed());

        $bad = JsonShape::validate(
            ['user' => ['name' => 1]],
            ['user' => JsonShape::object(['name' => 'string'])],
        );
        self::assertTrue($bad->failed());
        self::assertNotNull($bad->first('user.name'));
    }

    public function testNestedObjectRejectsNonObjectValue(): void
    {
        $r = JsonShape::validate(
            ['user' => [1, 2]],
            ['user' => JsonShape::object(['x' => 'string'])],
        );
        self::assertTrue($r->failed());
        self::assertStringContainsString('object', (string) $r->first('user'));
    }

    public function testOptionalNestedObject(): void
    {
        self::assertFalse(JsonShape::validate([], [
            'meta' => JsonShape::object(['k' => 'string'], optional: true),
        ])->failed());

        self::assertFalse(JsonShape::validate(['meta' => null], [
            'meta' => JsonShape::object(['k' => 'string'], optional: true),
        ])->failed());
    }

    public function testDeeplyNestedObject(): void
    {
        $data = ['a' => ['b' => ['c' => 'ok']]];
        $shape = [
            'a' => JsonShape::object([
                'b' => JsonShape::object(['c' => 'string']),
            ]),
        ];
        self::assertFalse(JsonShape::validate($data, $shape)->failed());
    }

    public function testRawArrayShapeSpecThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JsonShape::validate([], ['x' => ['y' => 'string']]);
    }
}
