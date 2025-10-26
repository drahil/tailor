<?php

declare(strict_types=1);

namespace drahil\Tailor\Support\DTOs;

use drahil\Tailor\Support\SessionTracker;
use Exception;

/**
 * Data Transfer Object for complete session data.
 *
 * Combines metadata with command/variable data from SessionTracker.
 */
final readonly class SessionData
{
    /**
     * @param SessionMetadata $metadata
     * @param array<int, array{code: string, output: string|null, timestamp: string, order: int}> $commands
     * @param array<string, array{type: string, class: string|null, value: string|null}> $variables
     * @param array{total_commands: int, duration_seconds: float, project_path: string, started_at: string|null} $sessionMetadata
     */
    public function __construct(
        public SessionMetadata $metadata,
        public array $commands,
        public array $variables,
        public array $sessionMetadata,
    ) {}

    /**
     * Create SessionData from SessionTracker and metadata.
     */
    public static function fromTracker(
        SessionMetadata $metadata,
        SessionTracker $tracker
    ): self {
        return new self(
            metadata: $metadata->withUpdatedTimestamp(),
            commands: $tracker->getCommands(),
            variables: $tracker->getVariables(),
            sessionMetadata: [
                'total_commands' => $tracker->getCommandCount(),
                'duration_seconds' => $tracker->getDuration(),
                'project_path' => app()->basePath(),
                'started_at' => $tracker->getStartedAt()?->format('c'),
            ],
        );
    }

    /**
     * Create SessionData from array data (e.g., loaded from JSON).
     *
     * @param array<string, mixed> $data
     * @throws Exception
     */
    public static function fromArray(array $data): self
    {
        $metadata = SessionMetadata::fromArray($data);

        return new self(
            metadata: $metadata,
            commands: $data['commands'] ?? [],
            variables: $data['variables'] ?? [],
            sessionMetadata: $data['metadata'] ?? [],
        );
    }

    /**
     * Convert to array for storage/serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge(
            $this->metadata->toArray(),
            [
                'commands' => $this->commands,
                'variables' => $this->variables,
                'metadata' => $this->sessionMetadata,
            ]
        );
    }

    /**
     * Get the number of commands in this session.
     */
    public function getCommandCount(): int
    {
        return count($this->commands);
    }

    /**
     * Check if session has commands.
     */
    public function hasCommands(): bool
    {
        return ! empty($this->commands);
    }

    /**
     * Check if session has variables.
     */
    public function hasVariables(): bool
    {
        return ! empty($this->variables);
    }
}
