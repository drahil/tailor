<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

use Illuminate\Contracts\Container\Singleton;
use Psy\Util\Str;

/**
 * Captures command history from history files.
 *
 * Reads and processes shell history files to extract
 * commands for session tracking.
 */
#[Singleton]
readonly class HistoryCaptureService
{
    private const LIBEDIT_HEADER = '_HiStOrY_V2_';

    public function __construct(
        private CommandFilterService $filter
    ) {}

    /**
     * Mark the starting point of the current session in the history file.
     *
     * This sets the session start line in the tracker so that only commands
     * from the current session are captured, not the entire history.
     */
    public function markSessionStart(SessionTracker $tracker, string $historyFile): void
    {
        $line = $this->countLines($historyFile);

        $tracker->setSessionStartLine($line);
        $tracker->setAppendStartLine($line);
    }

    /**
     * Mark the point after which session:append collects commands.
     */
    public function markAppendStart(SessionTracker $tracker, string $historyFile): void
    {
        $tracker->setAppendStartLine($this->countLines($historyFile));
    }

    /**
     * Capture commands from history file and add to tracker.
     *
     * Reads the history file from the session start line,
     * filters out skippable commands, and adds them to the tracker.
     *
     * @param SessionTracker $tracker
     * @param string $historyFile
     * @return void
     */
    public function captureHistoryToTracker(SessionTracker $tracker, string $historyFile): void
    {
        $lines = $this->readLines($historyFile);
        if ($lines === null) {
            return;
        }

        $tracker->clear();

        foreach ($this->extractCommands($lines, $tracker->getSessionStartLine()) as $command) {
            $tracker->addCommand($command);
        }
    }

    /**
     * Get the commands recorded in the history file from the given line on.
     *
     * @return array<int, string>
     */
    public function captureCommandsSince(string $historyFile, int $startLine): array
    {
        $lines = $this->readLines($historyFile);

        return $lines === null ? [] : $this->extractCommands($lines, $startLine);
    }

    /**
     * @param array<int, string> $lines
     * @return array<int, string>
     */
    private function extractCommands(array $lines, int $startLine): array
    {
        $isLibedit = ($lines[0] ?? null) === self::LIBEDIT_HEADER;
        $commands = [];

        foreach (array_slice($lines, $startLine) as $entry) {
            if ($entry === self::LIBEDIT_HEADER) {
                continue;
            }

            if ($isLibedit) {
                $entry = Str::unvis($entry);
            }

            $entry = trim($entry);

            if (empty($entry)) {
                continue;
            }

            if ($this->filter->shouldSkipCommand($entry)) {
                continue;
            }

            $commands[] = $entry;
        }

        return $commands;
    }

    /**
     * @return array<int, string>|null
     */
    private function readLines(string $historyFile): ?array
    {
        if (! file_exists($historyFile)) {
            return null;
        }

        $lines = file($historyFile, FILE_IGNORE_NEW_LINES);

        return $lines === false ? null : $lines;
    }

    private function countLines(string $historyFile): int
    {
        return count($this->readLines($historyFile) ?? []);
    }
}
