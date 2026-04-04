<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Http\Request;
use Vortex\Support\JsonSchemaValidator;

final class JsonSchemaValidatorTest extends TestCase
{
    public function testValidObjectPasses(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'count' => ['type' => 'integer'],
            ],
            'required' => ['name', 'count'],
        ];
        $r = JsonSchemaValidator::validateArray(['name' => 'x', 'count' => 3], $schema);
        self::assertFalse($r->failed());
    }

    public function testMissingRequiredYieldsError(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => ['name' => ['type' => 'string']],
            'required' => ['name'],
        ];
        $r = JsonSchemaValidator::validateArray([], $schema);
        self::assertTrue($r->failed());
        self::assertArrayHasKey('name', $r->errors());
    }

    public function testValidateDecodedListRoot(): void
    {
        $schema = [
            'type' => 'array',
            'items' => ['type' => 'integer'],
        ];
        $r = JsonSchemaValidator::validateDecoded([1, 2, 3], $schema);
        self::assertFalse($r->failed());

        $rBad = JsonSchemaValidator::validateDecoded([1, 'x'], $schema);
        self::assertTrue($rBad->failed());
        self::assertArrayHasKey('1', $rBad->errors());
    }

    public function testNestedArrayPathUsesDotIndices(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => ['id' => ['type' => 'integer']],
                        'required' => ['id'],
                    ],
                ],
            ],
            'required' => ['items'],
        ];
        $r = JsonSchemaValidator::validateArray(['items' => [['id' => 'bad']]], $schema);
        self::assertTrue($r->failed());
        self::assertArrayHasKey('items.0.id', $r->errors());
    }

    public function testBodyJsonSchemaResponseOnRequest(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => ['ok' => ['type' => 'boolean']],
            'required' => ['ok'],
        ];
        $req = new Request('POST', '/api', [], ['ok' => true], [], [], [], []);
        self::assertNull($req->bodyJsonSchemaResponse($schema));

        $badReq = new Request('POST', '/api', [], ['ok' => 'no'], [], [], [], []);
        $resp = $badReq->bodyJsonSchemaResponse($schema);
        self::assertNotNull($resp);
        $payload = json_decode($resp->body(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($payload['ok']);
        self::assertSame('validation_failed', $payload['error']);
        self::assertArrayHasKey('ok', $payload['errors']);
    }
}
