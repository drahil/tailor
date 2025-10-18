<?php

declare(strict_types=1);

namespace drahil\Tailor\Support\ValueObjects;

use drahil\Tailor\Support\Validation\Attributes\MaxLength;
use drahil\Tailor\Support\Validation\AttributeValidator;
use Stringable;

/**
 * Value object representing a session description.
 *
 * Ensures descriptions are within reasonable length limits.
 */
final readonly class SessionDescription implements Stringable
{
    public function __construct(
        #[MaxLength(
            length: 500,
            message: 'Session description is too long'
        )]
        private string $value
    ) {
        AttributeValidator::validateOrFail($this);
    }

    /**
     * Create a SessionDescription from a nullable string.
     *
     * Returns null if the input is null or empty.
     */
    public static function fromNullable(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return new self($value);
    }

    /**
     * Create a SessionDescription from a string.
     */
    public static function from(string $value): self
    {
        return new self($value);
    }

    /**
     * Get the string value.
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * String representation.
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Get the description length.
     */
    public function length(): int
    {
        return mb_strlen($this->value);
    }

    /**
     * Check if description is empty.
     */
    public function isEmpty(): bool
    {
        return trim($this->value) === '';
    }
}
