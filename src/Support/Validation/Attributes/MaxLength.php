<?php

declare(strict_types=1);

namespace drahil\Tailor\Support\Validation\Attributes;

use Attribute;

/**
 * Validates that a string value does not exceed a maximum length.
 *
 * @example #[MaxLength(255, message: 'Description too long')]
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class MaxLength
{
    public function __construct(
        public int $length,
        public string $message = 'Value exceeds maximum length',
    ) {}

    /**
     * Validate that the value does not exceed max length.
     */
    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return mb_strlen((string) $value) <= $this->length;
    }

    /**
     * Get formatted message with actual length info.
     */
    public function getFormattedMessage(mixed $value): string
    {
        return sprintf(
            '%s (maximum: %d, actual: %d)',
            $this->message,
            $this->length,
            mb_strlen((string) $value)
        );
    }
}
