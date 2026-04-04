<?php

declare(strict_types=1);

namespace Vortex\Support;

use InvalidArgumentException;
use Vortex\Validation\ValidationResult;

/**
 * Lightweight structural checks for decoded JSON bodies (assoc arrays). Not JSON Schema.
 *
 * Shape keys are field names; values are type strings: {@code string}, {@code int}, {@code float}, {@code bool},
 * {@code number} (int or float), {@code array} (any array), {@code list} (sequential array), {@code object} (non-list array).
 * Prefix with {@code ?} for optional keys (may be absent; {@code null} is accepted without a further type check).
 */
final class JsonShape
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $shape
     */
    public static function validate(array $data, array $shape): ValidationResult
    {
        $errors = [];
        foreach ($shape as $field => $spec) {
            if (! is_string($spec)) {
                throw new InvalidArgumentException('JsonShape expects string type specs per field.');
            }
            [$optional, $type] = self::parseSpec($spec);
            if (! array_key_exists($field, $data)) {
                if (! $optional) {
                    $errors[$field] = self::requiredMessage($field);
                }

                continue;
            }
            $value = $data[$field];
            if ($optional && $value === null) {
                continue;
            }
            $msg = self::typeMismatch($field, $value, $type);
            if ($msg !== null) {
                $errors[$field] = $msg;
            }
        }

        return ValidationResult::make($errors);
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private static function parseSpec(string $spec): array
    {
        $spec = trim($spec);
        if ($spec === '') {
            throw new InvalidArgumentException('JsonShape type spec cannot be empty.');
        }
        $optional = str_starts_with($spec, '?');
        $type = $optional ? trim(substr($spec, 1)) : $spec;
        if ($type === '') {
            throw new InvalidArgumentException('JsonShape type missing after ? prefix.');
        }

        return [$optional, strtolower($type)];
    }

    private static function requiredMessage(string $field): string
    {
        $attr = str_replace('_', ' ', $field);

        return "The {$attr} field is required.";
    }

    private static function typeMismatch(string $field, mixed $value, string $type): ?string
    {
        $attr = str_replace('_', ' ', $field);
        $ok = match ($type) {
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'bool' => is_bool($value),
            'number' => is_int($value) || is_float($value),
            'array' => is_array($value),
            'list' => is_array($value) && array_is_list($value),
            'object' => is_array($value) && ($value === [] || ! array_is_list($value)),
            default => throw new InvalidArgumentException("Unknown JsonShape type [{$type}]."),
        };

        return $ok ? null : "The {$attr} must be of type {$type}.";
    }
}
