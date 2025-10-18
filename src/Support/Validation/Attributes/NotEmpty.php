<?php

declare(strict_types=1);

namespace drahil\Tailor\Support\Validation\Attributes;

use Attribute;

/**
 * Validates that a value is not empty.
 *
 * @example #[NotEmpty(message: 'Name is required')]
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class NotEmpty
{
    public function __construct(
        public string $message = 'Value cannot be empty',
    ) {}

    /**
     * Validate that the value is not empty.
     */
    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value) && empty($value)) {
            return false;
        }

        return true;
    }
}
