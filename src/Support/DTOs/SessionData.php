<?php

declare(strict_types=1);

namespace drahil\Tailor\Support\DTOs;

use drahil\Tailor\Services\SessionTracker;

final readonly class SessionData
{
    /**
     * @param array<int, string> $commands
     */
    public function __construct(
        public SessionMetadata $metadata,
        public array $commands,
    ) {}

    public static function fromTracker(SessionMetadata $metadata, SessionTracker $tracker): self
    {
        return new self($metadata->withUpdatedTimestamp(), $tracker->getCommands());
    }

    public function getCommandCount(): int
    {
        return count($this->commands);
    }

    public function hasCommands(): bool
    {
        return $this->commands !== [];
    }
}
