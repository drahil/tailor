<?php

declare(strict_types=1);

namespace drahil\Tailor\Support\Validation;

/**
 * Result of a validation operation.
 */
final readonly class ValidationResult
{
    /**
     * @param array<string> $errors List of validation error messages
     */
    public function __construct(
        public array $errors = []
    ) {}

    /**
     * Check if validation failed (has errors).
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * Get the first error message.
     */
    public function firstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Get all error messages as a single string.
     */
    public function errorsAsString(string $separator = ', '): string
    {
        return implode($separator, $this->errors);
    }
}
