<?php

declare(strict_types=1);

namespace Vortex\Validation;

/**
 * Pipe-delimited rules per field, e.g. {@code 'email' => 'required|email|max:255'}.
 *
 * Supported: {@code required}, {@code nullable}, {@code email}, {@code string}, {@code min:n}, {@code max:n}, {@code confirmed}.
 */
final class Validator
{
    /**
     * @param array<string, mixed>         $data
     * @param array<string, string>        $rules
     * @param array<string, string>        $messages keys {@code field.rule} or {@code rule} fallback
     * @param array<string, string>        $attributes display names for {@code :attribute}
     */
    public static function make(
        array $data,
        array $rules,
        array $messages = [],
        array $attributes = [],
    ): ValidationResult {
        $errors = [];

        foreach ($rules as $field => $ruleLine) {
            $list = self::parseRules((string) $ruleLine);
            $value = $data[$field] ?? null;
            $nullable = in_array('nullable', $list, true);

            if ($nullable && self::isEmpty($value)) {
                continue;
            }

            foreach ($list as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }

                $message = self::check($field, $value, $rule, $data, $messages, $attributes);
                if ($message !== null) {
                    $errorField = str_starts_with($rule, 'confirmed') ? $field . '_confirmation' : $field;
                    $errors[$errorField] = $message;
                    break;
                }
            }
        }

        return ValidationResult::make($errors);
    }

    /**
     * @return list<string>
     */
    private static function parseRules(string $line): array
    {
        $parts = explode('|', $line);
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }

        return $out;
    }

    private static function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if ($value === '') {
            return true;
        }
        if (is_string($value) && trim($value) === '') {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     */
    private static function check(
        string $field,
        mixed $value,
        string $rule,
        array $data,
        array $messages,
        array $attributes,
    ): ?string {
        $colon = explode(':', $rule, 2);
        $name = $colon[0];
        $param = $colon[1] ?? null;

        return match ($name) {
            'required' => self::ruleRequired($field, $value, $messages, $attributes),
            'string' => self::ruleString($field, $value, $messages, $attributes),
            'email' => self::ruleEmail($field, $value, $messages, $attributes),
            'min' => self::ruleMin($field, $value, $param, $messages, $attributes),
            'max' => self::ruleMax($field, $value, $param, $messages, $attributes),
            'confirmed' => self::ruleConfirmed($field, $value, $data, $messages, $attributes),
            default => throw new \InvalidArgumentException("Unknown validation rule [{$name}]."),
        };
    }

    /**
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     */
    private static function ruleRequired(string $field, mixed $value, array $messages, array $attributes): ?string
    {
        if (self::isEmpty($value)) {
            return self::message($field, 'required', [], $messages, $attributes, 'The :attribute field is required.');
        }

        return null;
    }

    /**
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     */
    private static function ruleString(string $field, mixed $value, array $messages, array $attributes): ?string
    {
        if ($value === null || is_string($value)) {
            return null;
        }

        return self::message($field, 'string', [], $messages, $attributes, 'The :attribute must be a string.');
    }

    /**
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     */
    private static function ruleEmail(string $field, mixed $value, array $messages, array $attributes): ?string
    {
        if (self::isEmpty($value)) {
            return null;
        }
        $s = is_string($value) ? $value : (string) $value;
        if (filter_var($s, FILTER_VALIDATE_EMAIL) !== false) {
            return null;
        }

        return self::message($field, 'email', [], $messages, $attributes, 'The :attribute must be a valid email address.');
    }

    /**
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     */
    private static function ruleMin(string $field, mixed $value, ?string $param, array $messages, array $attributes): ?string
    {
        if ($param === null || ! ctype_digit($param)) {
            throw new \InvalidArgumentException('The min rule requires a numeric parameter (e.g. min:8).');
        }
        $min = (int) $param;
        if (self::isEmpty($value)) {
            return null;
        }
        $len = strlen(is_string($value) ? $value : (string) $value);
        if ($len >= $min) {
            return null;
        }

        return self::message(
            $field,
            'min',
            [':min' => (string) $min],
            $messages,
            $attributes,
            'The :attribute must be at least :min characters.',
        );
    }

    /**
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     */
    private static function ruleMax(string $field, mixed $value, ?string $param, array $messages, array $attributes): ?string
    {
        if ($param === null || ! ctype_digit($param)) {
            throw new \InvalidArgumentException('The max rule requires a numeric parameter (e.g. max:255).');
        }
        $max = (int) $param;
        if (self::isEmpty($value)) {
            return null;
        }
        $len = strlen(is_string($value) ? $value : (string) $value);
        if ($len <= $max) {
            return null;
        }

        return self::message(
            $field,
            'max',
            [':max' => (string) $max],
            $messages,
            $attributes,
            'The :attribute must not exceed :max characters.',
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     */
    private static function ruleConfirmed(string $field, mixed $value, array $data, array $messages, array $attributes): ?string
    {
        $key = $field . '_confirmation';
        $other = $data[$key] ?? null;
        if ($value === $other) {
            return null;
        }

        return self::message(
            $field,
            'confirmed',
            [],
            $messages,
            $attributes,
            'The :attribute confirmation does not match.',
        );
    }

    /**
     * @param array<string, string> $replacements e.g. [':min' => '8']
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     */
    private static function message(
        string $field,
        string $rule,
        array $replacements,
        array $messages,
        array $attributes,
        string $default,
    ): string {
        $keySpecific = "{$field}.{$rule}";
        $template = $messages[$keySpecific] ?? $messages[$rule] ?? $default;
        $attr = $attributes[$field] ?? str_replace('_', ' ', $field);
        $out = str_replace(':attribute', $attr, $template);
        foreach ($replacements as $k => $v) {
            $out = str_replace($k, $v, $out);
        }

        return $out;
    }
}
