<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

class SessionTracker
{
    /**
     * Commands entered in this process.
     *
     * @var array<int, string>
     */
    protected array $commands = [];

    /**
     * Saved session that new commands are appended to.
     */
    protected ?string $activeSession = null;

    /**
     * Index in $commands from which session:append collects commands.
     */
    protected int $appendStart = 0;

    public function setActiveSession(?string $name): void
    {
        $this->activeSession = $name;
    }

    public function getActiveSession(): ?string
    {
        return $this->activeSession;
    }

    public function markAppendStart(): void
    {
        $this->appendStart = count($this->commands);
    }

    /**
     * @return array<int, string>
     */
    public function getCommandsSinceAppendStart(): array
    {
        return array_slice($this->commands, $this->appendStart);
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
}
