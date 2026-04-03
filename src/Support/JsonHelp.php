<?php

declare(strict_types=1);

namespace Vortex\Support;

use JsonException;

/**
 * JSON encode/decode with consistent flags and {@see JsonException} for strict paths.
 */
final class JsonHelp
{
    public const DEFAULT_ENCODE_FLAGS = JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    /**
     * @throws JsonException
     */
    public static function encode(mixed $value, int $flags = self::DEFAULT_ENCODE_FLAGS): string
    {
        return json_encode($value, $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * Decode to an array (object JSON becomes associative array). Throws if JSON is invalid or root is not array/object.
     *
     * @throws JsonException
     */
    public static function decodeArray(string $json, int $depth = 512): array
    {
        if ($json === '') {
            throw new JsonException('Empty JSON string');
        }

        $data = json_decode($json, true, $depth, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new JsonException('JSON root must be an object or array');
        }

        return $data;
    }

    /**
     * Decode to array when valid; {@code null} on empty input, invalid JSON, or non-array/object root.
     */
    public static function tryDecodeArray(string $json, int $depth = 512): ?array
    {
        if ($json === '') {
            return null;
        }

        try {
            $data = json_decode($json, true, $depth, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }
}
