<?php

declare(strict_types=1);

namespace drahil\Tailor\Support\ValueObjects;

use drahil\Tailor\Support\Validation\ValidationException;
use drahil\Tailor\Support\Validation\ValidationResult;

/**
 * Value object representing a collection of session tags.
 *
 * Ensures uniqueness and enforces business rules on tag collections.
 */
final readonly class SessionTags
{
    private const MAX_TAGS = 10;

    /** @var array<SessionTag> */
    private array $tags;

    /**
     * @param array<string|SessionTag> $tags
     */
    public function __construct(array $tags = [])
    {
        $normalized = array_map(
            fn($tag) => $tag instanceof SessionTag ? $tag : SessionTag::from($tag),
            $tags
        );

        $this->tags = $this->removeDuplicates($normalized);

        if (count($this->tags) > self::MAX_TAGS) {
            throw new ValidationException(
                new ValidationResult(["tags: Cannot exceed " . self::MAX_TAGS . " tags"])
            );
        }
    }

    /**
     * Create SessionTags from an array of strings.
     *
     * @param array<string> $tags
     */
    public static function fromArray(array $tags): self
    {
        return new self($tags);
    }

    /**
     * Create an empty SessionTags collection.
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Convert to array of strings.
     *
     * @return array<string>
     */
    public function toArray(): array
    {
        return array_map(fn(SessionTag $tag) => $tag->toString(), $this->tags);
    }

    /**
     * Check if the collection contains a specific tag.
     */
    public function contains(SessionTag|string $tag): bool
    {
        $searchTag = $tag instanceof SessionTag ? $tag : SessionTag::from($tag);

        foreach ($this->tags as $existingTag) {
            if ($existingTag->equals($searchTag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the collection is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->tags);
    }

    /**
     * Get the count of tags.
     */
    public function count(): int
    {
        return count($this->tags);
    }

    /**
     * Add tags to the collection.
     *
     * @param array<string|SessionTag> $tags
     */
    public function add(array $tags): self
    {
        return new self(array_merge($this->tags, $tags));
    }

    /**
     * Remove tags from the collection.
     *
     * @param array<string|SessionTag> $tags
     */
    public function remove(array $tags): self
    {
        $toRemove = array_map(
            fn($tag) => $tag instanceof SessionTag ? $tag : SessionTag::from($tag),
            $tags
        );

        $filtered = array_filter(
            $this->tags,
            function (SessionTag $tag) use ($toRemove) {
                foreach ($toRemove as $removeTag) {
                    if ($tag->equals($removeTag)) {
                        return false;
                    }
                }
                return true;
            }
        );

        return new self($filtered);
    }

    /**
     * Check if the collection has all specified tags.
     *
     * @param array<string|SessionTag> $tags
     */
    public function hasAll(array $tags): bool
    {
        foreach ($tags as $tag) {
            if (! $this->contains($tag)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Remove duplicate tags from the array.
     *
     * @param array<SessionTag> $tags
     * @return array<SessionTag>
     */
    private function removeDuplicates(array $tags): array
    {
        $unique = [];
        $seen = [];

        foreach ($tags as $tag) {
            $value = $tag->toString();
            if (! in_array($value, $seen, true)) {
                $seen[] = $value;
                $unique[] = $tag;
            }
        }

        return $unique;
    }
}
