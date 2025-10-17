<?php

declare(strict_types=1);

namespace drahil\Tailor\Console\Commands;

use drahil\Tailor\PsySH\SessionListCommand;
use drahil\Tailor\PsySH\SessionExecuteCommand;
use drahil\Tailor\PsySH\SessionSaveCommand;
use drahil\Tailor\PsySH\SessionDeleteCommand;
use drahil\Tailor\Support\SessionTracker;
use drahil\Tailor\Support\SessionManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psy\Shell;
use Psy\Configuration;

class TailorCommand extends Command
{
    protected SessionTracker $sessionTracker;
    protected SessionManager $sessionManager;

    public function __construct()
    {
        parent::__construct('tailor');

        $this->sessionTracker = new SessionTracker();
        $this->sessionManager = new SessionManager();
    }

    public function configure(): void
    {
        $this->setDescription('Enhanced Laravel Tinker with session management');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = new Configuration([
            'startupMessage' => $this->getStartupMessage(),
            'historyFile' => storage_path('tailor/tailor_history'),
        ]);

        $config->addCommands([
            new SessionListCommand(),
            new SessionSaveCommand(),
            new SessionExecuteCommand(),
            new SessionDeleteCommand(),
        ]);

        $shell = new Shell($config);

        $this->markSessionStart();

        $this->hookSessionTracker($shell);

        $shell->run();

        return Command::SUCCESS;
    }

    /**
     * Mark the starting point of the current session in the history file
     */
    protected function markSessionStart(): void
    {
        $historyFile = storage_path('tailor/tailor_history');

        if (file_exists($historyFile)) {
            $lines = file($historyFile, FILE_IGNORE_NEW_LINES);
            $startLine = count($lines);
        } else {
            $startLine = 0;
        }

        $this->sessionTracker->setSessionStartLine($startLine);
    }

    protected function hookSessionTracker(Shell $shell): void
    {
        $shell->setScopeVariables([
            '__sessionTracker' => $this->sessionTracker,
            '__sessionManager' => $this->sessionManager,
        ]);
    }

    protected function getStartupMessage(): string
    {
        return <<<MESSAGE
<fg=cyan>Tailor - Enhanced Laravel Tinker</>

Available commands:
  session:list       List all saved sessions
  session:save       Save current session
  session:execute    Execute a saved session
  session:delete     Delete a saved session

Type 'help' for more commands
MESSAGE;
    }
}
