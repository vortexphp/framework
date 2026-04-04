<?php

declare(strict_types=1);

namespace Vortex\Http;

use Vortex\Support\JsonSchemaValidator;

/**
 * Maps a domain object (model, array, DTO) to a JSON-serializable array for HTTP APIs.
 */
abstract class JsonResource
{
    /** @var list<callable(array<string, mixed>): array<string, mixed>> */
    private array $responseTransforms = [];

    public function __construct(protected mixed $resource)
    {
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * Register a transform run after {@see toArray()} and before {@see transformResponse()}, in registration order.
     * Typical use: call from the resource constructor.
     *
     * @param callable(array<string, mixed>): array<string, mixed> $transform
     */
    protected function pushResponseTransform(callable $transform): void
    {
        $this->responseTransforms[] = $transform;
    }

    /**
     * Clone with extra transforms appended (immutable-style); does not mutate this instance.
     *
     * @param callable(array<string, mixed>): array<string, mixed> ...$transforms
     */
    public function withResponseTransforms(callable ...$transforms): static
    {
        $copy = clone $this;
        foreach ($transforms as $t) {
            $copy->responseTransforms[] = $t;
        }

        return $copy;
    }

    /**
     * Base mapping from {@see $resource} — each pushed transform, then {@see transformResponse()}.
     *
     * @return array<string, mixed>
     */
    public function resolve(): array
    {
        $data = $this->toArray();
        foreach ($this->responseTransforms as $fn) {
            $data = $fn($data);
        }

        return $this->transformResponse($data);
    }

    /**
     * Override to append metadata, strip fields for certain clients, or run small transforms without growing {@see toArray()}.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function transformResponse(array $data): array
    {
        return $data;
    }

    /**
     * @param bool $wrap When true, body is {@see Response::apiOk}; otherwise JSON for {@see resolve()} output.
     */
    public function toResponse(int $status = 200, bool $wrap = true): Response
    {
        $payload = $this->resolve();

        return $wrap ? Response::apiOk($payload, $status) : Response::json($payload, $status);
    }

    /**
     * @param array<string, mixed>|object $schema JSON Schema for {@see resolve()} output
     */
    public function toValidatedResponse(array|object $schema, int $status = 200, bool $wrap = true): Response
    {
        $payload = $this->resolve();
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
            $out[] = (new $class($item))->resolve();
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
