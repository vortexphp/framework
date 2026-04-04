<?php

declare(strict_types=1);

namespace Vortex\Http;

use Vortex\Support\JsonSchemaValidator;

/**
 * Maps a domain object (model, array, DTO) to a JSON-serializable array for HTTP APIs.
 */
abstract class JsonResource
{
    public function __construct(protected mixed $resource)
    {
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * @param bool $wrap When true, body is {@see Response::apiOk}; otherwise raw {@see toArray()} as JSON object.
     */
    public function toResponse(int $status = 200, bool $wrap = true): Response
    {
        $payload = $this->toArray();

        return $wrap ? Response::apiOk($payload, $status) : Response::json($payload, $status);
    }

    /**
     * @param array<string, mixed>|object $schema JSON Schema for {@see toArray()} output
     */
    public function toValidatedResponse(array|object $schema, int $status = 200, bool $wrap = true): Response
    {
        $payload = $this->toArray();
        $result = JsonSchemaValidator::validateDecoded($payload, $schema);
        if ($result->failed()) {
            return Response::apiError(500, 'response_schema_mismatch', 'Response failed JSON Schema validation', [
                'errors' => $result->errors(),
            ]);
        }

        return $wrap ? Response::apiOk($payload, $status) : Response::json($payload, $status);
    }

    /**
     * @template R of JsonResource
     *
     * @param iterable<mixed> $items
     * @param class-string<R> $class
     *
     * @return list<array<string, mixed>>
     */
    public static function collect(iterable $items, string $class): array
    {
        $out = [];
        foreach ($items as $item) {
            $out[] = (new $class($item))->toArray();
        }

        return $out;
    }

    /**
     * @template R of JsonResource
     *
     * @param iterable<mixed> $items
     * @param class-string<R> $class
     */
    public static function collectionResponse(iterable $items, string $class, int $status = 200, bool $wrap = true): Response
    {
        $list = self::collect($items, $class);

        return $wrap ? Response::apiOk($list, $status) : Response::json($list, $status);
    }

    /**
     * @template R of JsonResource
     *
     * @param iterable<mixed> $items
     * @param class-string<R> $class
     * @param array<string, mixed>|object $schema Schema for the JSON array root (e.g. `{ "type": "array", "items": { ... } }`)
     */
    public static function collectionValidatedResponse(
        iterable $items,
        string $class,
        array|object $schema,
        int $status = 200,
        bool $wrap = true,
    ): Response {
        $list = self::collect($items, $class);
        $result = JsonSchemaValidator::validateDecoded($list, $schema);
        if ($result->failed()) {
            return Response::apiError(500, 'response_schema_mismatch', 'Response failed JSON Schema validation', [
                'errors' => $result->errors(),
            ]);
        }

        return $wrap ? Response::apiOk($list, $status) : Response::json($list, $status);
    }
}
