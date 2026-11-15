<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

class SessionTracker
{
    /**
     * Commands executed in the current session.
     *
     * @var array<int, string>
     */
    protected array $commands = [];

    /**
     * Line number in history file where this session started.
     */
    protected int $sessionStartLine = 0;

    /**
     * Saved session that new commands are appended to.
     */
    protected ?string $activeSession = null;

    /**
     * History line from which session:append collects commands.
     */
    protected int $appendStartLine = 0;

    public function setSessionStartLine(int $line): void
    {
        $this->sessionStartLine = $line;
    }

    public function getSessionStartLine(): int
    {
        return $this->sessionStartLine;
    }

    public function setActiveSession(?string $name): void
    {
        $this->activeSession = $name;
    }

    public function getActiveSession(): ?string
    {
        return $this->activeSession;
    }

    public function setAppendStartLine(int $line): void
    {
        $this->appendStartLine = $line;
    }

    public function getAppendStartLine(): int
    {
        return $this->appendStartLine;
    }

    public function addCommand(string $code): void
    {
        $this->commands[] = $code;
    }

    /**
     * @return array<int, string>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    public function getCommandCount(): int
    {
        return count($this->commands);
    }

    public function hasCommands(): bool
    {
        return $this->commands !== [];
    }

    public function clear(): void
    {
        $this->commands = [];
    }
}
