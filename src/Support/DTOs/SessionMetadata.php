<?php

declare(strict_types=1);

namespace drahil\Tailor\Support\DTOs;

use DateTime;
use drahil\Tailor\Support\ValueObjects\SessionDescription;
use drahil\Tailor\Support\ValueObjects\SessionName;
use Exception;

/**
 * Data Transfer Object for session metadata.
 *
 * Represents the non-command data associated with a session.
 */
final readonly class SessionMetadata
{
    /**
     * @param SessionName $name
     * @param SessionDescription|null $description
     * @param array<string> $tags
     * @param DateTime|null $createdAt
     * @param DateTime|null $updatedAt
     * @param string|null $laravelVersion
     * @param string|null $phpVersion
     */
    public function __construct(
        public SessionName $name,
        public ?SessionDescription $description = null,
        public array $tags = [],
        public ?DateTime $createdAt = null,
        public ?DateTime $updatedAt = null,
        public ?string $laravelVersion = null,
        public ?string $phpVersion = null,
    ) {}

    /**
     * Create SessionMetadata from array data (e.g., from JSON).
     * @throws Exception
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: SessionName::from($data['name']),
            description: SessionDescription::fromNullable($data['description'] ?? null),
            tags: $data['tags'] ?? [],
            createdAt: isset($data['created_at'])
                ? new DateTime($data['created_at'])
                : null,
            updatedAt: isset($data['updated_at'])
                ? new DateTime($data['updated_at'])
                : null,
            laravelVersion: $data['laravel_version'] ?? null,
            phpVersion: $data['php_version'] ?? null,
        );
    }

    /**
     * Convert to array for storage/serialization.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name->toString(),
            'description' => $this->description?->toString(),
            'tags' => $this->tags,
            'created_at' => $this->createdAt?->format('c'),
            'updated_at' => $this->updatedAt?->format('c'),
            'laravel_version' => $this->laravelVersion,
            'php_version' => $this->phpVersion,
        ];
    }

    /**
     * Create a new instance with updated timestamp.
     */
    public function withUpdatedTimestamp(): self
    {
        return new self(
            name: $this->name,
            description: $this->description,
            tags: $this->tags,
            createdAt: $this->createdAt ?? new DateTime(),
            updatedAt: new DateTime(),
            laravelVersion: $this->laravelVersion,
            phpVersion: $this->phpVersion,
        );
    }

    /**
     * Check if session has tags.
     */
    public function hasTags(): bool
    {
        return ! empty($this->tags);
    }

    /**
     * Check if session has description.
     */
    public function hasDescription(): bool
    {
        return $this->description !== null;
    }
}
