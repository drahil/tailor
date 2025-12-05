<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

use Illuminate\Contracts\Container\Singleton;

/**
 * Filters commands based on predefined skip patterns.
 *
 * Determines which commands should be excluded from session
 * tracking and execution (e.g., internal commands, navigation).
 */
#[Singleton]
class CommandFilterService
{
    /**
     * Patterns for commands that should be skipped.
     *
     * @var array<string>
     */
    private const SKIP_PATTERNS = [
        '/^session:/',
        '/^help\b/',
        '/^exit\b/',
        '/^quit\b/',
        '/^history\b/',
        '/^clear\b/',
    ];

    /**
     * Check if a command should be skipped from tracking.
     *
     * @param string $command The command to check
     * @return bool True if command should be skipped, false otherwise
     */
    public function shouldSkipCommand(string $command): bool
    {
        $trimmedCommand = trim($command);

        if (empty($trimmedCommand)) {
            return true;
        }

        foreach (self::SKIP_PATTERNS as $pattern) {
            if (preg_match($pattern, $trimmedCommand)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filter an array of commands, removing skippable ones.
     *
     * @param array<string> $commands
     * @return array<string>
     */
    public function filterCommands(array $commands): array
    {
        return array_filter(
            $commands,
            fn(string $command) => ! $this->shouldSkipCommand($command)
        );
    }

    /**
     * Get the list of skip patterns.
     *
     * @return array<string>
     */
    public function getSkipPatterns(): array
    {
        return self::SKIP_PATTERNS;
    }
}
