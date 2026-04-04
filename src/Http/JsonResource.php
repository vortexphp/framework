<?php

declare(strict_types=1);

namespace Vortex\Http;

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
}
