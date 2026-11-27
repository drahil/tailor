<?php

declare(strict_types=1);

namespace drahil\Tailor\Support\DTOs;

use DateTime;
use drahil\Tailor\Support\ValueObjects\SessionDescription;
use drahil\Tailor\Support\ValueObjects\SessionName;
use Exception;

final readonly class SessionMetadata
{
    /**
     * @param array<string> $tags
     */
    public function __construct(
        public SessionName $name,
        public ?SessionDescription $description = null,
        public array $tags = [],
        public ?DateTime $createdAt = null,
        public ?DateTime $updatedAt = null,
    ) {}

    /**
     * Create SessionMetadata from array data, as stored in legacy JSON sessions.
     *
     * @param array<string, mixed> $data
     * @throws Exception
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: SessionName::from($data['name']),
            description: SessionDescription::fromNullable($data['description'] ?? null),
            tags: $data['tags'] ?? [],
            createdAt: isset($data['created_at']) ? new DateTime($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTime($data['updated_at']) : null,
        );
    }

    public function withUpdatedTimestamp(): self
    {
        return new self(
            name: $this->name,
            description: $this->description,
            tags: $this->tags,
            createdAt: $this->createdAt ?? new DateTime(),
            updatedAt: new DateTime(),
        );
    }

    public function hasTags(): bool
    {
        return ! empty($this->tags);
    }

    public function hasDescription(): bool
    {
        return $this->description !== null;
    }
}
