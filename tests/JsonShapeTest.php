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
}
