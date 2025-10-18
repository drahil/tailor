<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH;

use drahil\Tailor\Support\SessionManager;
use Psy\Command\Command;
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
     * @param OutputInterface $output The output interface
     * @param string $question The question to ask the user
     * @return bool True if the user confirms (y/yes), false otherwise
     */
    protected function confirm(OutputInterface $output, string $question): bool
    {
        $output->write("<question>{$question} [y/N]</question> ");

        $handle = fopen('php://stdin', 'r');
        $line = fgets($handle);
        fclose($handle);

        $answer = trim(strtolower($line));

        return in_array($answer, ['y', 'yes'], true);
    }
}
