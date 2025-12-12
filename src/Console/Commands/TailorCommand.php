<?php

declare(strict_types=1);

namespace drahil\Tailor\Console\Commands;

use drahil\Tailor\PsySH\AutoSaveLoopListener;
use drahil\Tailor\PsySH\SessionCommands\SessionDeleteCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionExecuteCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionListCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionSaveCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionViewCommand;
use drahil\Tailor\PsySH\TailorAutoCompleter;
use drahil\Tailor\Services\AutoSaveService;
use drahil\Tailor\Services\ClassAutoImporter;
use drahil\Tailor\Services\HistoryCaptureService;
use drahil\Tailor\Services\IncludeFileManager;
use drahil\Tailor\Services\SessionManager;
use drahil\Tailor\Services\SessionTracker;
use drahil\Tailor\Services\ShellFactory;
use drahil\Tailor\Support\Formatting\SessionOutputFormatter;
use Psy\Configuration;
use Psy\Shell;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TailorCommand extends Command
{
    protected SessionTracker $sessionTracker;
    protected SessionManager $sessionManager;
    protected AutoSaveService $autoSaveService;

    public function __construct(
        private readonly ClassAutoImporter $autoImporter,
        private readonly ShellFactory $shellFactory,
        private readonly IncludeFileManager $fileManager,
        private readonly HistoryCaptureService $historyCaptureService,
    ) {
        parent::__construct('tailor');

        $this->sessionTracker = new SessionTracker();
        $this->sessionManager = new SessionManager();
        $this->autoSaveService = new AutoSaveService(
            $this->sessionManager,
            $this->sessionTracker,
            $this->historyCaptureService
        );
    }

    public function configure(): void
    {
        $this->setDescription('Enhanced Laravel Tinker with session management and auto-imported classes');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $importResult = $this->autoImporter->prepare($output);

        if (! $importResult->isSuccessful()) {
            return Command::FAILURE;
        }

        $config = new Configuration([
            'startupMessage' => $this->getStartupMessage(),
            'historyFile' => storage_path('tailor/tailor_history'),
        ]);

        $autoCompleter = new TailorAutoCompleter();
        $config->setAutoCompleter($autoCompleter);

        $this->shellFactory->addLaravelCasters($config);

        $formatter = app(SessionOutputFormatter::class);

        $config->addCommands([
            new SessionListCommand(),
            new SessionSaveCommand(app(HistoryCaptureService::class), $formatter),
            new SessionExecuteCommand($formatter),
            new SessionDeleteCommand(),
            new SessionViewCommand($formatter),
        ]);

        $shell = new Shell($config);

        /** Manually set the context on the autocompleter since Shell doesn't do it automatically */
        $reflection = new ReflectionClass($shell);
        $contextProperty = $reflection->getProperty('context');
        $contextProperty->setAccessible(true);
        $context = $contextProperty->getValue($shell);
        $autoCompleter->setContext($context);

        if ($importResult->hasIncludeFile()) {
            $shell->setIncludes([$importResult->getIncludeFile()]);
        }

        $this->markSessionStart();

        $this->hookSessionTracker($shell);

        $this->setupAutoSave($shell);

        $shell->run();

        if ($importResult->hasIncludeFile()) {
            $this->fileManager->cleanup($importResult->getIncludeFile());
        }

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

    protected function setupAutoSave(Shell $shell): void
    {
        $listener = new AutoSaveLoopListener($this->autoSaveService);

        $reflection = new ReflectionClass($shell);
        $property = $reflection->getProperty('loopListeners');
        $property->setAccessible(true);

        $listeners = $property->getValue($shell);
        $listeners[] = $listener;
        $property->setValue($shell, $listeners);
    }

    protected function getStartupMessage(): string
    {
        return <<<MESSAGE
<fg=cyan>Tailor - Enhanced Laravel Tinker</>

Classes from your App namespace are auto-imported.
Type 'User::' and press Tab for autocomplete.

Available commands:
  session:list       List all saved sessions
  session:save       Save current session
  session:view       View session details and commands
  session:execute    Execute a saved session
  session:delete     Delete a saved session

Type 'help' for more commands
MESSAGE;
    }
}
