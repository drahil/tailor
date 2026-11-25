<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH;

use drahil\Tailor\Services\SessionTracker;
use Psy\Readline\Readline;
use Psy\Readline\Transient;

/**
 * Records every code entry of this process in the session tracker, then hands it to the real readline.
 */
class RecordingReadline implements Readline
{
    private Readline $inner;

    private ?SessionTracker $tracker = null;

    /**
     * @var array<string, true>
     */
    private array $commandNames = [];

    public function __construct($historyFile = null, $historySize = 0, $eraseDups = false, ?Readline $inner = null)
    {
        $this->inner = $inner ?? new Transient($historyFile, $historySize, $eraseDups);
    }

    public static function wrap(Readline $inner, SessionTracker $tracker): self
    {
        $readline = new self(inner: $inner);
        $readline->tracker = $tracker;

        return $readline;
    }

    /**
     * @param array<string> $names
     */
    public function setCommandNames(array $names): void
    {
        $this->commandNames = array_fill_keys($names, true);
    }

    public static function isSupported(): bool
    {
        return true;
    }

    public static function supportsBracketedPaste(): bool
    {
        return false;
    }

    public function addHistory(string $line): bool
    {
        $added = $this->inner->addHistory($line);
        $code = trim($line);

        if ($code !== '' && ! $this->isShellCommand($code)) {
            $this->tracker?->addCommand($code);
        }

        return $added;
    }

    public function clearHistory(): bool
    {
        return $this->inner->clearHistory();
    }

    public function listHistory(): array
    {
        return $this->inner->listHistory();
    }

    public function readHistory(): bool
    {
        return $this->inner->readHistory();
    }

    public function readline(?string $prompt = null)
    {
        return $this->inner->readline($prompt);
    }

    public function redisplay(): void
    {
        $this->inner->redisplay();
    }

    public function writeHistory(): bool
    {
        return $this->inner->writeHistory();
    }

    /**
     * Same check as Shell::hasCommand(): the first run of non-space characters names a shell command.
     */
    private function isShellCommand(string $code): bool
    {
        return preg_match('/([^\s]+?)(?:\s|$)/A', $code, $match) === 1
            && isset($this->commandNames[$match[1]]);
    }
}
