<?php

declare(strict_types=1);

namespace drahil\Tailor\Console\Commands;

use drahil\Tailor\PsySH\SessionListCommand;
use drahil\Tailor\PsySH\SessionExecuteCommand;
use drahil\Tailor\PsySH\SessionSaveCommand;
use drahil\Tailor\PsySH\SessionDeleteCommand;
use drahil\Tailor\PsySH\SessionUpdateCommand;
use drahil\Tailor\PsySH\SessionViewCommand;
use drahil\Tailor\Support\DTOs\SessionData;
use drahil\Tailor\Support\Formatting\SessionOutputFormatter;
use drahil\Tailor\Support\HistoryCaptureService;
use drahil\Tailor\Support\SessionCommandRunner;
use drahil\Tailor\Support\SessionTracker;
use drahil\Tailor\Support\SessionManager;
use drahil\Tailor\Support\ValueObjects\SessionName;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Psy\Shell;
use Psy\Configuration;

class TailorCommand extends Command
{
    protected SessionTracker $sessionTracker;
    protected SessionManager $sessionManager;
    protected HistoryCaptureService $historyService;
    protected SessionCommandRunner $commandRunner;

    public function __construct()
    {
        parent::__construct('tailor');

        $this->sessionTracker = new SessionTracker();
        $this->sessionManager = new SessionManager();
        $this->historyService = app(HistoryCaptureService::class);
        $this->commandRunner = app(SessionCommandRunner::class);
    }

    public function configure(): void
    {
        $this->setDescription('Enhanced Laravel Tinker with session management')
            ->addOption(
                'session',
                's',
                InputOption::VALUE_REQUIRED,
                'Load a saved session to continue working on it'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $sessionName = $input->getOption('session');
        $sessionToExecute = null;

        if ($sessionName) {
            $sessionToExecute = $this->loadSessionData($sessionName, $output);
            if ($sessionToExecute === null) {
                return Command::FAILURE;
            }
        }

        $config = new Configuration([
            'startupMessage' => $this->getStartupMessage($sessionName),
            'historyFile' => $this->historyService->getHistoryPath(),
        ]);

        $formatter = app(SessionOutputFormatter::class);

        $config->addCommands([
            new SessionListCommand(),
            new SessionSaveCommand(app(HistoryCaptureService::class), $formatter),
            new SessionExecuteCommand($formatter, $this->commandRunner),
            new SessionDeleteCommand(),
            new SessionViewCommand($formatter),
            new SessionUpdateCommand(app(HistoryCaptureService::class)),
        ]);

        $shell = new Shell($config);

        $this->historyService->markSessionStart($this->sessionTracker);

        $this->hookSessionTracker($shell);

        if ($sessionToExecute !== null) {
            $shell->setOutput($output);
            $this->commandRunner->executeWithSummary(
                $shell,
                $sessionToExecute,
                $this->sessionTracker,
                $output
            );
        }

        $shell->run();

        return Command::SUCCESS;
    }

    /**
     * Load session data from storage.
     */
    protected function loadSessionData(string $sessionName, OutputInterface $output): ?SessionData
    {
        try {
            $session = $this->sessionManager->load(SessionName::from($sessionName));
            $output->writeln("<info>Loading session: {$sessionName}</info>");
            return $session;
        } catch (\Exception $e) {
            $output->writeln("<error>Failed to load session '{$sessionName}': {$e->getMessage()}</error>");
            return null;
        }
    }

    protected function hookSessionTracker(Shell $shell): void
    {
        $shell->setScopeVariables([
            '__sessionTracker' => $this->sessionTracker,
            '__sessionManager' => $this->sessionManager,
        ]);
    }

    protected function getStartupMessage(?string $loadedSession = null): string
    {
        $sessionInfo = $loadedSession
            ? "\n<fg=green>Session loaded: {$loadedSession}</>\n"
            : '';

        return <<<MESSAGE
<fg=cyan>Tailor - Enhanced Laravel Tinker</>{$sessionInfo}

Available commands:
  session:list       List all saved sessions
  session:save       Save current session
  session:view       View session details and commands
  session:execute    Execute a saved session
  session:update     Update the loaded session with new commands
  session:delete     Delete a saved session

Type 'help' for more commands
MESSAGE;
    }
}
