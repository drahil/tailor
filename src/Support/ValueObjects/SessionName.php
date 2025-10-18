<?php

declare(strict_types=1);

namespace drahil\Tailor\Support\ValueObjects;

use drahil\Tailor\Support\Validation\Attributes\NotEmpty;
use drahil\Tailor\Support\Validation\Attributes\Pattern;
use drahil\Tailor\Support\Validation\AttributeValidator;
use Stringable;

/**
 * Value object representing a session name.
 *
 * Ensures session names are valid and immutable.
 */
final readonly class SessionName implements Stringable
{
    public function __construct(
        #[NotEmpty(message: 'Session name cannot be empty')]
        #[Pattern(
            pattern: '/^[a-zA-Z0-9_-]+$/',
            message: 'Session name must contain only alphanumeric characters, hyphens, and underscores'
        )]
        private string $value
    ) {
        AttributeValidator::validateOrFail($this);
    }

    /**
     * Create a SessionName from a nullable string.
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
     * Create a SessionName from a string.
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
     * Check equality with another SessionName.
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
