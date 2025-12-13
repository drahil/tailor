<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

use drahil\Tailor\PsySH\AutoSaveLoopListener;
use drahil\Tailor\PsySH\SessionCommands\SessionDeleteCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionEditCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionExecuteCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionListCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionSaveCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionViewCommand;
use drahil\Tailor\PsySH\TailorAutoCompleter;
use drahil\Tailor\Support\DTOs\ImportResult;
use drahil\Tailor\Support\Formatting\SessionOutputFormatter;
use Psy\Configuration;
use Psy\Shell;
use ReflectionClass;

class TailorShellBuilder
{
    public function __construct(
        private readonly SessionTracker $sessionTracker,
        private readonly SessionManager $sessionManager,
        private readonly AutoSaveService $autoSaveService,
        private readonly HistoryCaptureService $historyCaptureService,
        private readonly SessionOutputFormatter $formatter,
    ) {}

    public function build(ImportResult $importResult): Shell
    {
        $config = $this->createConfiguration();
        $autoCompleter = $this->setupAutoCompleter($config);

        $this->addLaravelCasters($config);
        $this->registerCommands($config);

        $shell = new Shell($config);

        $this->setAutoCompleterContext($shell, $autoCompleter);

        if ($importResult->hasIncludeFile()) {
            $shell->setIncludes([$importResult->getIncludeFile()]);
        }

        $historyFile = storage_path('tailor/tailor_history');
        $this->historyCaptureService->markSessionStart($this->sessionTracker, $historyFile);
        $this->hookSessionTracker($shell);
        $this->setupAutoSave($shell);

        return $shell;
    }

    private function createConfiguration(): Configuration
    {
        return new Configuration([
            'startupMessage' => $this->getStartupMessage(),
            'historyFile' => storage_path('tailor/tailor_history'),
        ]);
    }

    private function setupAutoCompleter(Configuration $config): TailorAutoCompleter
    {
        $autoCompleter = new TailorAutoCompleter();
        $config->setAutoCompleter($autoCompleter);

        return $autoCompleter;
    }

    private function setAutoCompleterContext(Shell $shell, TailorAutoCompleter $autoCompleter): void
    {
        $reflection = new ReflectionClass($shell);
        $contextProperty = $reflection->getProperty('context');
        $contextProperty->setAccessible(true);
        $context = $contextProperty->getValue($shell);
        $autoCompleter->setContext($context);
    }

    private function addLaravelCasters(Configuration $config): void
    {
        if (! class_exists('Laravel\Tinker\TinkerCaster')) {
            return;
        }

        $casters = [
            'Illuminate\Support\Collection' => 'Laravel\Tinker\TinkerCaster::castCollection',
            'Illuminate\Support\HtmlString' => 'Laravel\Tinker\TinkerCaster::castHtmlString',
            'Illuminate\Support\Stringable' => 'Laravel\Tinker\TinkerCaster::castStringable',
        ];

        if (class_exists('Illuminate\Database\Eloquent\Model')) {
            $casters['Illuminate\Database\Eloquent\Model'] = 'Laravel\Tinker\TinkerCaster::castModel';
        }

        if (class_exists('Illuminate\Process\ProcessResult')) {
            $casters['Illuminate\Process\ProcessResult'] = 'Laravel\Tinker\TinkerCaster::castProcessResult';
        }

        if (class_exists('Illuminate\Foundation\Application')) {
            $casters['Illuminate\Foundation\Application'] = 'Laravel\Tinker\TinkerCaster::castApplication';
        }

        $config->getPresenter()->addCasters($casters);
    }

    private function registerCommands(Configuration $config): void
    {
        $config->addCommands([
            new SessionListCommand(),
            new SessionSaveCommand($this->historyCaptureService, $this->formatter),
            new SessionExecuteCommand($this->formatter),
            new SessionDeleteCommand(),
            new SessionViewCommand($this->formatter),
            new SessionEditCommand(),
        ]);
    }

    private function hookSessionTracker(Shell $shell): void
    {
        $shell->setScopeVariables([
            '__sessionTracker' => $this->sessionTracker,
            '__sessionManager' => $this->sessionManager,
        ]);
    }

    private function setupAutoSave(Shell $shell): void
    {
        $listener = new AutoSaveLoopListener($this->autoSaveService);

        $reflection = new ReflectionClass($shell);
        $property = $reflection->getProperty('loopListeners');
        $property->setAccessible(true);

        $listeners = $property->getValue($shell);
        $listeners[] = $listener;
        $property->setValue($shell, $listeners);
    }

    private function getStartupMessage(): string
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
  session:edit       Edit session metadata
  session:delete     Delete a saved session

Type 'help' for more commands
MESSAGE;
    }
}
