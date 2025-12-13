<?php

declare(strict_types=1);

namespace drahil\Tailor\Support\ValueObjects;

use drahil\Tailor\Support\Validation\Attributes\MaxLength;
use drahil\Tailor\Support\Validation\Attributes\NotEmpty;
use drahil\Tailor\Support\Validation\Attributes\Pattern;
use drahil\Tailor\Support\Validation\AttributeValidator;
use Stringable;

/**
 * Value object representing a session tag.
 *
 * Ensures tags are valid, normalized, and immutable.
 */
final readonly class SessionTag implements Stringable
{
    public function __construct(
        #[NotEmpty(message: 'Tag cannot be empty')]
        #[Pattern(
            pattern: '/^[a-zA-Z0-9_-]+$/',
            message: 'Tag must contain only alphanumeric characters, hyphens, and underscores'
        )]
        #[MaxLength(length: 50, message: 'Tag cannot exceed 50 characters')]
        private string $value
    ) {
        AttributeValidator::validateOrFail($this);
    }

    /**
     * Create a SessionTag from a string, normalizing to lowercase.
     */
    public static function from(string $value): self
    {
        return new self(strtolower(trim($value)));
    }

    /**
     * Create multiple SessionTag instances from an array of strings.
     *
     * @param array<string> $tags
     * @return array<SessionTag>
     */
    public static function fromArray(array $tags): array
    {
        return array_map(fn(string $tag) => self::from($tag), $tags);
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
     * Check equality with another tag.
     */
    public function equals(SessionTag $other): bool
    {
        return $this->value === $other->value;
    }
}
