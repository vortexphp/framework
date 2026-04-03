<?php

declare(strict_types=1);

namespace Vortex\Validation;

final class ValidationResult
{
    /**
     * @param array<string, string> $errors first error message per field
     */
    private function __construct(
        private readonly array $errors,
    ) {
    }

    /**
     * @param array<string, string> $errors
     */
    public static function make(array $errors): self
    {
        return new self($errors);
    }

    public function failed(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function first(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }
}
