<?php

declare(strict_types=1);

namespace drahil\Tailor\Support;

use DateTime;
use DateTimeInterface;
use Throwable;

class SessionTracker
{
    /**
     * Commands executed in the current session.
     *
     * @var array
     */
    protected array $commands = [];

    /**
     * Variables in the current scope.
     *
     * @var array
     */
    protected array $variables = [];

    /**
     * Session start time.
     *
     * @var DateTimeInterface|null
     */
    protected ?DateTimeInterface $startedAt = null;

    /**
     * Line number in history file where this session started.
     *
     * @var int
     */
    protected int $sessionStartLine = 0;

    /**
     * Create a new SessionTracker instance.
     */
    public function __construct()
    {
        $this->startedAt = new DateTime();
    }

    /**
     * Set the line number in history file where this session started.
     *
     * @param int $line
     * @return void
     */
    public function setSessionStartLine(int $line): void
    {
        $this->sessionStartLine = $line;
    }

    /**
     * Get the line number where this session started.
     *
     * @return int
     */
    public function getSessionStartLine(): int
    {
        return $this->sessionStartLine;
    }

    /**
     * Add a command to the tracker.
     *
     * @param string $code The code that was executed
     * @param mixed $output The output from executing the code (optional)
     * @return void
     */
    public function addCommand(string $code, mixed $output = null): void
    {
        $this->commands[] = [
            'code' => $code,
            'output' => $this->serializeOutput($output),
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            'order' => count($this->commands) + 1,
        ];
    }

    /**
     * Get all tracked commands.
     *
     * @return array
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Get the total number of commands tracked.
     *
     * @return int
     */
    public function getCommandCount(): int
    {
        return count($this->commands);
    }

    /**
     * Clear all tracked commands.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->commands = [];
        $this->variables = [];
        $this->startedAt = new \DateTime();
    }

    /**
     * Load commands from an array (used when restoring a session).
     *
     * @param array $commands
     * @return void
     */
    public function loadCommands(array $commands): void
    {
        $this->commands = $commands;
    }

    /**
     * Track a variable in the current scope.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function trackVariable(string $name, mixed $value): void
    {
        $this->variables[$name] = $this->serializeVariable($value);
    }

    /**
     * Get all tracked variables.
     *
     * @return array
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * Get session start time.
     *
     * @return DateTimeInterface|null
     */
    public function getStartedAt(): ?DateTimeInterface
    {
        return $this->startedAt;
    }

    /**
     * Get session duration in seconds.
     *
     * @return int
     */
    public function getDuration(): int
    {
        if (! $this->startedAt) {
            return 0;
        }

        return (new DateTime())->getTimestamp() - $this->startedAt->getTimestamp();
    }

    /**
     * Serialize output for storage.
     *
     * @param mixed $output
     * @return string|null
     */
    protected function serializeOutput(mixed $output): ?string
    {
        if ($output === null) {
            return null;
        }

        try {
            if (is_object($output)) {
                if (method_exists($output, '__toString')) {
                    return (string) $output;
                }
                return get_class($output) . ' Object';
            }

            if (is_array($output) || is_scalar($output)) {
                return json_encode($output, JSON_PRETTY_PRINT);
            }

            return var_export($output, true);
        } catch (Throwable $e) {
            return 'Unable to serialize output';
        }
    }

    /**
     * Serialize a variable for storage.
     *
     * @param mixed $value
     * @return array
     */
    protected function serializeVariable(mixed $value): array
    {
        return [
            'type' => gettype($value),
            'class' => is_object($value) ? get_class($value) : null,
            'value' => $this->serializeOutput($value),
        ];
    }

    /**
     * Check if the tracker has any commands.
     *
     * @return bool
     */
    public function hasCommands(): bool
    {
        return $this->getCommandCount() > 0;
    }

    /**
     * Get the last executed command.
     *
     * @return array|null
     */
    public function getLastCommand(): ?array
    {
        if (empty($this->commands)) {
            return null;
        }

        return end($this->commands);
    }
}
