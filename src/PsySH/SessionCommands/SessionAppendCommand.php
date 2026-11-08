<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH\SessionCommands;

use drahil\Tailor\Services\HistoryCaptureService;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SessionAppendCommand extends SessionCommand
{
    public function __construct(
        private readonly HistoryCaptureService $historyCaptureService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('session:append')
            ->setAliases(['append'])
            ->setDescription('Append new commands to a saved session')
            ->setHelp(<<<HELP
  Append the commands run since the last session:execute or session:append
  to a saved session. Without a name, the last executed session is used.

  Usage:
    session:append                 Append to the active session
    session:append my-session      Append to a specific session

  Examples:
    >>> session:execute my-session
    >>> \$post->comments()->count()
    >>> session:append
  HELP
            )
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'Name of the session (defaults to the active session)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sessionManager = $this->getSessionManager();
        $sessionTracker = $this->getApplication()->getScopeVariable('__sessionTracker');

        $nameInput = $input->getArgument('name') ?? $sessionTracker->getActiveSession();
        if ($nameInput === null) {
            $output->writeln("<error>No active session. Run 'session:execute my-session' first, or pass a session name.</error>");
            return 1;
        }

        $sessionName = $this->validateSessionName($nameInput, $output);
        if (! $sessionName) {
            return 1;
        }

        if (! $this->sessionExists($sessionName->toString())) {
            return $this->sessionNotFoundError($output, (string) $sessionName);
        }

        $historyFile = storage_path('tailor/tailor_history');
        $commands = $this->historyCaptureService->captureCommandsSince($historyFile, $sessionTracker->getAppendStartLine());

        if ($commands === []) {
            $output->writeln('<comment>No new commands to append.</comment>');
            return 0;
        }

        try {
            $sessionData = $sessionManager->append($sessionName, $commands);
        } catch (Exception $e) {
            return $this->operationFailedError($output, 'append to', $e->getMessage());
        }

        $this->historyCaptureService->markAppendStart($sessionTracker, $historyFile);

        $appendedCount = count($commands);
        $output->writeln(
            "<info>✓ Appended {$appendedCount} command(s) to '{$sessionName}' ({$sessionData->getCommandCount()} total)</info>"
        );

        return 0;
    }
}
