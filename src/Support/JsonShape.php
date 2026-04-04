<?php

declare(strict_types=1);

namespace Vortex\Support;

use InvalidArgumentException;
use Vortex\Validation\ValidationResult;

/**
 * Lightweight structural checks for decoded JSON bodies (assoc arrays). Not JSON Schema.
 *
 * Shape keys are field names; values are either:
 * - a type string: {@code string}, {@code int}, {@code float}, {@code bool}, {@code number}, {@code array}, {@code list}, {@code object}
 *   (prefix {@code ?} for optional keys; {@code null} skips type check when optional), or
 * - {@see object() nested object} (recursive shapes).
 * - {@see listOf() list of objects} with the same object schema per index (errors use {@code items.0.field} paths).
 *
 * Note: JSON {@code {}} and {@code []} both decode to PHP {@code []}; nested {@code object()} treats empty {@code []} as an empty object.
 */
final class JsonShape
{
    /**
     * Nested object field. Inner keys use the same rules as the root shape.
     *
     * @param array<string, string|array> $fields
     *
     * @return array{_shape: 'object', optional: bool, fields: array<string, string|array>}
     */
    public static function object(array $fields, bool $optional = false): array
    {
        return ['_shape' => 'object', 'optional' => $optional, 'fields' => $fields];
    }

    /**
     * Sequential list where every item matches the given {@see object()} schema.
     *
     * @return array{_shape: 'list', optional: bool, element: array<string, mixed>}
     */
    public static function listOf(array $elementObject, bool $optional = false): array
    {
        if (($elementObject['_shape'] ?? '') !== 'object') {
            throw new InvalidArgumentException('JsonShape::listOf() expects JsonShape::object([...]).');
        }

        return ['_shape' => 'list', 'optional' => $optional, 'element' => $elementObject];
    }

    /**
     * @param array<string, string|array> $fields Per-item object fields (same as {@see object()}).
     *
     * @return array{_shape: 'list', optional: bool, element: array<string, mixed>}
     */
    public static function listOfObjects(array $fields, bool $optional = false): array
    {
        return self::listOf(self::object($fields), $optional);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|array> $shape
     */
    public static function validate(array $data, array $shape): ValidationResult
    {
        return self::validateAt($data, $shape, '');
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|array> $shape
     */
    private static function validateAt(array $data, array $shape, string $prefix): ValidationResult
    {
        $errors = [];
        foreach ($shape as $field => $spec) {
            $path = $prefix === '' ? $field : $prefix . '.' . $field;

            if (is_array($spec) && ($spec['_shape'] ?? '') === 'list') {
                self::walkList($data, $field, $path, $spec, $errors);

                continue;
            }

            if (is_array($spec) && ($spec['_shape'] ?? '') === 'object') {
                self::walkObject($data, $field, $path, $spec, $errors);

                continue;
            }

            if (is_array($spec)) {
                throw new InvalidArgumentException(
                    'JsonShape field spec must be a type string or JsonShape::object([...]).',
                );
            }

            [$optional, $type] = self::parseSpec($spec);
            if (! array_key_exists($field, $data)) {
                if (! $optional) {
                    $errors[$path] = self::requiredMessage($path);
                }

                continue;
            }
            $value = $data[$field];
            if ($optional && $value === null) {
                continue;
            }
            $msg = self::typeMismatch($path, $value, $type);
            if ($msg !== null) {
                $errors[$path] = $msg;
            }
        }

        return ValidationResult::make($errors);
    }

    /**
     * @param array<string, mixed> $spec
     * @param array<string, string> $errors Reference; merged inner validation messages.
     */
    private static function walkObject(array $data, string $field, string $path, array $spec, array &$errors): void
    {
        $optional = $spec['optional'] ?? false;
        /** @var array<string, string|array> $innerShape */
        $innerShape = $spec['fields'];

        if (! array_key_exists($field, $data)) {
            if (! $optional) {
                $errors[$path] = self::requiredMessage($path);
            }

            return;
        }

        $value = $data[$field];
        if ($optional && $value === null) {
            return;
        }

        if (! self::isJsonObjectArray($value)) {
            $errors[$path] = self::objectTypeMessage($path);

            return;
        }

        /** @var array<string, mixed> $value */
        $inner = self::validateAt($value, $innerShape, $path);
        foreach ($inner->errors() as $key => $message) {
            $errors[$key] = $message;
        }
    }

    /**
     * @param array<string, mixed> $spec
     * @param array<string, string> $errors
     */
    private static function walkList(array $data, string $field, string $path, array $spec, array &$errors): void
    {
        $optional = $spec['optional'] ?? false;
        $elementObject = $spec['element'];
        if (($elementObject['_shape'] ?? '') !== 'object') {
            throw new InvalidArgumentException('JsonShape list element must be JsonShape::object([...]).');
        }
        /** @var array<string, string|array> $innerFields */
        $innerFields = $elementObject['fields'];

        if (! array_key_exists($field, $data)) {
            if (! $optional) {
                $errors[$path] = self::requiredMessage($path);
            }

            return;
        }

        $value = $data[$field];
        if ($optional && $value === null) {
            return;
        }

        if (! is_array($value) || ! array_is_list($value)) {
            $attr = str_replace('_', ' ', $path);
            $errors[$path] = "The {$attr} must be a list.";

            return;
        }

        foreach ($value as $i => $item) {
            $ip = $path . '.' . $i;
            if (! self::isJsonObjectArray($item)) {
                $errors[$ip] = self::objectTypeMessage($ip);

                continue;
            }
            /** @var array<string, mixed> $item */
            $inner = self::validateAt($item, $innerFields, $ip);
            foreach ($inner->errors() as $key => $message) {
                $errors[$key] = $message;
            }
        }
    }

    private static function isJsonObjectArray(mixed $value): bool
    {
        return is_array($value) && ($value === [] || ! array_is_list($value));
    }

    private static function objectTypeMessage(string $path): string
    {
        $attr = str_replace('_', ' ', $path);

        return "The {$attr} must be an object.";
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

    private static function requiredMessage(string $path): string
    {
        $attr = str_replace('_', ' ', $path);

        return "The {$attr} field is required.";
    }

    private static function typeMismatch(string $path, mixed $value, string $type): ?string
    {
        $attr = str_replace('_', ' ', $path);
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
