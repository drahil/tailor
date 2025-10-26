<?php

declare(strict_types=1);

namespace drahil\Tailor\Support;

use Illuminate\Contracts\Container\Singleton;
use Illuminate\Support\Facades\Storage;

/**
 * Captures command history from history files.
 *
 * Reads and processes shell history files to extract
 * commands for session tracking. Also manages history
 * file paths and session start line tracking.
 */
#[Singleton]
class HistoryCaptureService
{
    /**
     * Default history file path.
     */
    private const DEFAULT_HISTORY_PATH = 'tailor/tailor_history';

    public function __construct(
        private readonly CommandFilterService $filter
    ) {}

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
        if (! file_exists($historyFile)) {
            return;
        }

        $lines = file($historyFile, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        $sessionStartLine = $tracker->getSessionStartLine();
        $currentSessionLines = array_slice($lines, $sessionStartLine);

        $tracker->clear();

        foreach ($currentSessionLines as $entry) {
            $entry = trim($entry);

            if (empty($entry)) {
                continue;
            }

            if ($this->filter->shouldSkipCommand($entry)) {
                continue;
            }

            $tracker->addCommand($entry);
        }
    }

    /**
     * Capture commands from history file starting at specific line.
     *
     * @param string $historyFile
     * @param int $startLine
     * @return array<int, string>
     */
    public function captureFromLine(string $historyFile, int $startLine): array
    {
        if (! file_exists($historyFile)) {
            return [];
        }

        $lines = file($historyFile, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $currentSessionLines = array_slice($lines, $startLine);

        return $this->filter->filterCommands(
            array_map('trim', $currentSessionLines)
        );
    }

    /**
     * Get total line count from history file.
     *
     * @param string $historyFile
     * @return int
     */
    public function getLineCount(string $historyFile): int
    {
        if (! file_exists($historyFile)) {
            return 0;
        }

        $lines = file($historyFile, FILE_IGNORE_NEW_LINES);
        return $lines !== false ? count($lines) : 0;
    }

    /**
     * Get the history file path.
     *
     * Returns the configured history file path or default.
     *
     * @return string
     */
    public function getHistoryPath(): string
    {
        return Storage::disk('local')->path(self::DEFAULT_HISTORY_PATH);
    }

    /**
     * Get the current line number in the history file.
     *
     * Returns the total number of lines currently in the history file,
     * which can be used as a session start marker.
     *
     * @return int
     */
    public function getCurrentLineNumber(): int
    {
        return $this->getLineCount($this->getHistoryPath());
    }

    /**
     * Mark the session start in the tracker.
     *
     * Sets the current history file line number as the session start point.
     * This allows tracking which commands belong to the current session.
     *
     * @param SessionTracker $tracker The session tracker to update
     * @return void
     */
    public function markSessionStart(SessionTracker $tracker): void
    {
        $startLine = $this->getCurrentLineNumber();
        $tracker->setSessionStartLine($startLine);
    }
}
