<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH\SessionCommands;

use drahil\Tailor\Services\SessionManager;
use drahil\Tailor\Support\Validation\ValidationException;
use drahil\Tailor\Support\ValueObjects\SessionName;
use Psy\Command\Command;
use Psy\Util\Str;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Abstract base class for session-related commands.
 *
 * This class provides common infrastructure for all session commands,
 * including access to the SessionManager service and reusable helper
 * methods for validation and error handling.
 */
abstract class SessionCommand extends Command
{

    /**
     * Get the SessionManager from the PsySH application scope.
     *
     * @return SessionManager
     */
    protected function getSessionManager(): SessionManager
    {
        return $this->getApplication()->getScopeVariable('__sessionManager');
    }

    /**
     * Output a session not found error message.
     *
     * @param OutputInterface $output The output interface
     * @param string $name The session name that was not found
     * @return int The error exit code (1)
     */
    protected function sessionNotFoundError(OutputInterface $output, string $name): int
    {
        $output->writeln("<error>Session '{$name}' does not exist.</error>");
        return 1;
    }

    /**
     * Output a generic error message.
     *
     * @param OutputInterface $output The output interface
     * @param string $action The action that failed (e.g., "save", "delete")
     * @param string $message The error message
     * @return int The error exit code (1)
     */
    protected function operationFailedError(OutputInterface $output, string $action, string $message): int
    {
        $output->writeln("<error>Failed to {$action} session: {$message}</error>");
        return 1;
    }

    /**
     * Check if a session exists.
     *
     * @param string $name The session name to check
     * @return bool True if the session exists, false otherwise
     */
    protected function sessionExists(string $name): bool
    {
        return $this->getSessionManager()->exists($name);
    }

    /**
     * Prompt the user for a yes/no confirmation.
     *
     * @return bool True if the user confirms (y/yes), false otherwise
     */
    protected function confirm(InputInterface $input, OutputInterface $output, string $question): bool
    {
        $output->write("<question>{$question} [y/N]</question> ");

        $stream = $input instanceof StreamableInputInterface ? $input->getStream() : null;
        $line = fgets($stream ?? STDIN);

        if ($line === false) {
            $output->writeln('');
            return false;
        }

        return in_array(strtolower(trim($line)), ['y', 'yes'], true);
    }

    /**
     * Validate and retrieve session name from input.
     *
     * Handles validation and outputs appropriate error messages.
     * Returns null if validation fails (caller should return 1).
     *
     * @param string|null $nameInput The raw session name input
     * @param OutputInterface $output The output interface for error messages
     * @param bool $required Whether the name is required (true) or optional (false)
     * @return SessionName|null The validated session name, or null on validation failure
     */
    protected function validateSessionName(
        ?string $nameInput,
        OutputInterface $output,
        bool $required = true
    ): ?SessionName {
        try {
            return $required
                ? SessionName::from($nameInput ?? '')
                : SessionName::fromNullable($nameInput);
        } catch (ValidationException $e) {
            $output->writeln("<error>{$e->result->firstError()}</error>");
            return null;
        }
    }

    /**
     * Decode libedit-encoded code from sessions saved before history was decoded on capture.
     */
    protected function decodeCommandCode(string $code): string
    {
        if (str_contains($code, '\\040')) {
            return Str::unvis($code);
        }

        return $code;
    }
}
