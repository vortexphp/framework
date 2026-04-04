<?php

declare(strict_types=1);

namespace Vortex\Pagination;

use JsonException;

/**
 * Opaque cursor for {@see QueryBuilder::cursorPaginate()}: URL-safe base64 of a JSON object
 * (keys must match the paginated column name, e.g. {@code {"id": 42}}).
 */
final class Cursor
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function encode(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * @return array<string, mixed>>
     */
    public static function decode(string $token): array
    {
        $padded = strtr($token, '-_', '+/');
        $padLen = (4 - strlen($padded) % 4) % 4;
        $padded .= str_repeat('=', $padLen);
        $json = base64_decode($padded, true);
        if ($json === false || $json === '') {
            throw new InvalidCursorException('Invalid cursor encoding');
        }
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidCursorException('Invalid cursor payload', previous: $e);
        }
        if (! is_array($data) || $data === [] || array_is_list($data)) {
            throw new InvalidCursorException('Cursor must be a JSON object');
        }

        return $data;
    }
}
