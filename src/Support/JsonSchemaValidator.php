<?php

declare(strict_types=1);

namespace Vortex\Support;

use JsonException;
use JsonSchema\Constraints\BaseConstraint;
use JsonSchema\Validator;
use Vortex\Validation\ValidationResult;

/**
 * Validates decoded JSON (assoc arrays) against a JSON Schema object using **`justinrainbow/json-schema`**
 * (Draft 3–7). Prefer {@see \Vortex\Support\JsonShape} for lightweight structural checks without a schema dependency weight.
 */
final class JsonSchemaValidator
{
    /**
     * @param array<string, mixed> $data Request body or other decoded JSON as PHP associative arrays
     * @param array<string, mixed>|object $schema Schema as returned from **`json_decode(..., true)`** or **`false`** (object)
     */
    public static function validateArray(array $data, array|object $schema): ValidationResult
    {
        $schemaObj = is_object($schema) ? $schema : BaseConstraint::arrayToObjectRecursive($schema);

        try {
            $value = self::payloadToValidatorValue($data, $schemaObj);
        } catch (JsonException $e) {
            return ValidationResult::make(['_root' => 'Payload cannot be encoded for JSON Schema validation']);
        }

        $validator = new Validator();
        $validator->validate($value, $schemaObj);

        if ($validator->isValid()) {
            return ValidationResult::make([]);
        }

        $errors = [];
        foreach ($validator->getErrors() as $row) {
            $key = self::normalizeFieldKey((string) ($row['property'] ?? ''));
            if (! isset($errors[$key])) {
                $errors[$key] = (string) ($row['message'] ?? 'Invalid value');
            }
        }

        return ValidationResult::make($errors);
    }

    private static function normalizeFieldKey(string $property): string
    {
        if ($property === '') {
            return '_root';
        }

        $withDots = preg_replace('/\[(\d+)\]/', '.$1', $property) ?? $property;

        return ltrim($withDots, '.');
    }

    /**
     * PHP cannot distinguish JSON {@code {}} from {@code []} after {@code json_decode($json, true)}; both are {@code []}.
     * When the schema root is object-shaped, treat that empty array as an empty object.
     */
    private static function payloadToValidatorValue(array $data, object $schema): mixed
    {
        if ($data === [] && self::rootSchemaExpectsObject($schema) && ! self::rootSchemaExpectsArray($schema)) {
            return new \stdClass();
        }

        return json_decode(json_encode($data, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
    }

    private static function rootSchemaExpectsObject(object $schema): bool
    {
        $t = $schema->type ?? null;
        if ($t === 'object') {
            return true;
        }
        if (is_array($t) && in_array('object', $t, true)) {
            return true;
        }
        if (isset($schema->properties) && (is_object($schema->properties) || is_array($schema->properties))) {
            return true;
        }

        return false;
    }

    private static function rootSchemaExpectsArray(object $schema): bool
    {
        $t = $schema->type ?? null;
        if ($t === 'array') {
            return true;
        }

        return is_array($t) && in_array('array', $t, true);
    }
}
