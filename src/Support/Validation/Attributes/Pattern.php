<?php

declare(strict_types=1);

namespace drahil\Tailor\Support\Validation\Attributes;

use Attribute;

/**
 * Validates that a value matches a regular expression pattern.
 *
 * @example #[Pattern('/^[a-z]+$/', message: 'Must contain only lowercase letters')]
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class Pattern
{
    public function __construct(
        public string $pattern,
        public string $message = 'Value does not match the required pattern',
    ) {}

    /**
     * Validate the value against the pattern.
     */
    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true; // Use NotEmpty for null checks
        }

        return preg_match($this->pattern, (string) $value) === 1;
    }
}
