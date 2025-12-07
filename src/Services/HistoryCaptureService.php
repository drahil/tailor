<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

use Illuminate\Contracts\Container\Singleton;

/**
 * Captures command history from history files.
 *
 * Reads and processes shell history files to extract
 * commands for session tracking.
 */
#[Singleton]
readonly class HistoryCaptureService
{
    public function __construct(
        private CommandFilterService $filter
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
}
