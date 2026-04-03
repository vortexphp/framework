<?php

declare(strict_types=1);

namespace Vortex\Validation;

use InvalidArgumentException;

final class Rule
{
    /** @var list<string> */
    private array $rules = [];
    /** @var array<string, string> */
    private array $messages = [];

    private function __construct()
    {
    }

    public static function required(?string $message = null): self
    {
        return (new self())->push('required', $message);
    }

    public static function nullable(?string $message = null): self
    {
        return (new self())->push('nullable', $message);
    }

    public function email(?string $message = null): self
    {
        return $this->push('email', $message);
    }

    public function string(?string $message = null): self
    {
        return $this->push('string', $message);
    }

    public function confirmed(?string $message = null): self
    {
        return $this->push('confirmed', $message);
    }

    public function min(int $value, ?string $message = null): self
    {
        $this->guardNonNegative($value, 'min');

        return $this->push('min:' . $value, $message, 'min');
    }

    public function max(int $value, ?string $message = null): self
    {
        $this->guardNonNegative($value, 'max');

        return $this->push('max:' . $value, $message, 'max');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    public function __toString(): string
    {
        return implode('|', $this->rules);
    }

    private function push(string $rule, ?string $message = null, ?string $messageKey = null): self
    {
        if (! in_array($rule, $this->rules, true)) {
            $this->rules[] = $rule;
        }
        if ($message !== null && $message !== '') {
            $this->messages[$messageKey ?? self::ruleName($rule)] = $message;
        }

        return $this;
    }

    private static function ruleName(string $rule): string
    {
        $parts = explode(':', $rule, 2);

        return trim($parts[0]);
    }

    private function guardNonNegative(int $value, string $name): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException("The {$name} rule expects a non-negative integer.");
        }
    }
}
