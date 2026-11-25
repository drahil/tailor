<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

use drahil\Tailor\PsySH\AutoSaveLoopListener;
use drahil\Tailor\PsySH\RecordingReadline;
use drahil\Tailor\PsySH\SessionCommands\SessionAppendCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionDeleteCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionEditCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionExecuteCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionExportCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionListCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionRenameCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionSaveCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionViewCommand;
use drahil\Tailor\PsySH\TailorAutoCompleter;
use drahil\Tailor\Support\DTOs\ImportResult;
use drahil\Tailor\Support\Formatting\SessionOutputFormatter;
use Psy\Configuration;
use Psy\Readline\ShellReadlineInterface;
use Psy\Shell;
use ReflectionClass;

class TailorShellBuilder
{
    public function __construct(
        private readonly SessionTracker $sessionTracker,
        private readonly SessionManager $sessionManager,
        private readonly AutoSaveService $autoSaveService,
        private readonly SessionOutputFormatter $formatter,
    ) {}

    /**
     * @param array<int, string> $includes Files to run after the auto-imports
     */
    public function build(ImportResult $importResult, array $includes = []): Shell
    {
        $config = $this->createConfiguration();
        $readline = $this->setupRecordingReadline($config);
        $autoCompleter = $this->setupAutoCompleter($config);

        $this->addLaravelCasters($config);
        $this->registerCommands($config, $importResult);

        $shell = new Shell($config);

        $this->setAutoCompleterContext($shell, $autoCompleter);
        $commandNames = array_keys($shell->all());
        $autoCompleter->setCommandNames($commandNames);
        $readline?->setCommandNames($commandNames);

        $shell->setIncludes(array_merge(
            $importResult->hasIncludeFile() ? [$importResult->getIncludeFile()] : [],
            $includes,
        ));

        $this->hookSessionTracker($shell);
        $this->setupAutoSave($shell);

        return $shell;
    }

    private function createConfiguration(): Configuration
    {
        return new Configuration([
            'startupMessage' => $this->getStartupMessage(),
            'historyFile' => storage_path('tailor/tailor_history'),
            'historySize' => (int) config('tailor.history.limit'),
            'eraseDuplicates' => (bool) config('tailor.history.deduplicate'),
            'useTabCompletion' => (bool) config('tailor.autocomplete.enabled'),
        ]);
    }

    /**
     * The experimental interactive readline is left unwrapped, so commands are not recorded with it.
     */
    private function setupRecordingReadline(Configuration $config): ?RecordingReadline
    {
        $inner = $config->getReadline();

        if ($inner instanceof ShellReadlineInterface) {
            return null;
        }

        $readline = RecordingReadline::wrap($inner, $this->sessionTracker);
        $config->setReadline($readline);

        return $readline;
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

    private function registerCommands(Configuration $config, ImportResult $importResult): void
    {
        $config->addCommands([
            new SessionListCommand(),
            new SessionSaveCommand($this->formatter),
            new SessionExecuteCommand($this->formatter),
            new SessionAppendCommand(),
            new SessionDeleteCommand(),
            new SessionViewCommand($this->formatter),
            new SessionEditCommand(),
            new SessionRenameCommand(),
            new SessionExportCommand(new SessionExporter($importResult->getAliases())),
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
<fg=cyan>Tailor - a Laravel REPL with saved sessions</>

Classes from your App namespace are auto-imported.
Type 'User::' and press Tab for autocomplete.

Available commands:
  session:ls         List all saved sessions
  session:save       Save current session
  session:view       View session details and commands
  session:execute    Execute a saved session
  session:append     Append new commands to a saved session
  session:edit       Edit session metadata
  session:rename     Rename a saved session
  session:delete     Delete a saved session
  session:export     Export a session as a script or Artisan command

Type 'help' for more commands
MESSAGE;
    }
}
