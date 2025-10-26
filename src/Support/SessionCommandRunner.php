<?php

declare(strict_types=1);

namespace drahil\Tailor\Support;

use drahil\Tailor\Support\DTOs\SessionData;
use Exception;
use Illuminate\Contracts\Container\Singleton;
use Psy\Shell;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Executes session commands through PsySH shell.
 *
 * Handles the execution of saved session commands, tracking
 * success/failure counts, and updating the session tracker state.
 */
#[Singleton]
class SessionCommandRunner
{
    /**
     * Result of session execution.
     *
     * @var array{executed: int, failed: int}
     */
    private array $lastExecutionResult = [
        'executed' => 0,
        'failed' => 0,
    ];

    public function __construct(
        private readonly CommandDecoder $decoder
    ) {}

    /**
     * Execute all commands from a session.
     *
     * @param Shell $shell The PsySH shell instance
     * @param SessionData $sessionData The session data containing commands
     * @param SessionTracker $tracker The session tracker to update
     * @param OutputInterface $output The output interface for displaying results
     * @param bool $displayOutput Whether to display command output (default: true)
     * @return array{executed: int, failed: int} Execution statistics
     */
    public function execute(
        Shell $shell,
        SessionData $sessionData,
        SessionTracker $tracker,
        OutputInterface $output,
        bool $displayOutput = true
    ): array {
        $executedCount = 0;
        $failedCount = 0;

        foreach ($sessionData->commands as $command) {
            $code = $this->decoder->decode($command['code']);

            if ($code === '_HiStOrY_V2_') {
                continue;
            }

            try {
                if ($displayOutput) {
                    $output->writeln("<comment>>>> {$code}</comment>");
                }

                $result = $shell->execute($code);

                if ($result !== null && $displayOutput) {
                    $output->writeln("<info>=> " . var_export($result, true) . "</info>");
                }

                $executedCount++;
            } catch (Exception $e) {
                $failedCount++;
                if ($displayOutput) {
                    $output->writeln("<error>Failed to execute: {$e->getMessage()}</error>");
                }
            }
        }

        $tracker->loadCommands($sessionData->commands);

        $tracker->setLoadedSessionName($sessionData->metadata->name->toString());

        $this->lastExecutionResult = [
            'executed' => $executedCount,
            'failed' => $failedCount,
        ];

        return $this->lastExecutionResult;
    }

    /**
     * Execute session and display summary.
     *
     * Convenience method that executes the session and displays
     * a formatted summary of the execution results.
     *
     * @param Shell $shell The PsySH shell instance
     * @param SessionData $sessionData The session data containing commands
     * @param SessionTracker $tracker The session tracker to update
     * @param OutputInterface $output The output interface for displaying results
     * @return array{executed: int, failed: int} Execution statistics
     */
    public function executeWithSummary(
        Shell $shell,
        SessionData $sessionData,
        SessionTracker $tracker,
        OutputInterface $output
    ): array {
        $result = $this->execute($shell, $sessionData, $tracker, $output);

        $this->displayExecutionSummary($output, $result);

        return $result;
    }

    /**
     * Display execution summary.
     *
     * @param OutputInterface $output The output interface
     * @param array{executed: int, failed: int} $result Execution statistics
     * @return void
     */
    public function displayExecutionSummary(OutputInterface $output, array $result): void
    {
        $output->writeln('');
        $output->writeln("<info>Executed {$result['executed']} command(s)" .
            ($result['failed'] > 0 ? " ({$result['failed']} failed)" : "") . "</info>");
        $output->writeln("<comment>You can continue working and use 'session:update' to save new commands.</comment>");
        $output->writeln('');
    }

    /**
     * Get the last execution result.
     *
     * @return array{executed: int, failed: int}
     */
    public function getLastExecutionResult(): array
    {
        return $this->lastExecutionResult;
    }
}
