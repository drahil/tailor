<?php

declare(strict_types=1);

use drahil\Tailor\PsySH\SessionCommands\SessionAppendCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionDeleteCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionEditCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionExecuteCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionExportCommand;
use drahil\Tailor\Services\SessionExporter;
use drahil\Tailor\PsySH\SessionCommands\SessionListCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionSaveCommand;
use drahil\Tailor\PsySH\SessionCommands\SessionViewCommand;
use drahil\Tailor\Support\Formatting\SessionOutputFormatter;
use Psy\CodeAnalysis\BufferAnalyzer;
use Psy\Readline\Interactive\Input\StatementCompletenessPolicy;

test('bare command names are not buffered as incomplete PHP', function () {
    $formatter = Mockery::mock(SessionOutputFormatter::class);
    $commands = [
        new SessionListCommand(),
        new SessionSaveCommand($formatter),
        new SessionExecuteCommand($formatter),
        new SessionAppendCommand(),
        new SessionDeleteCommand(),
        new SessionViewCommand($formatter),
        new SessionEditCommand(),
        new SessionExportCommand(new SessionExporter()),
    ];
    $policy = new StatementCompletenessPolicy(new BufferAnalyzer(), false);

    foreach ($commands as $command) {
        foreach ([$command->getName(), ...$command->getAliases()] as $name) {
            expect($policy->isCompleteStatement($name))->toBeTrue("'{$name}' is read as incomplete PHP");
        }
    }
})->skip(! class_exists(StatementCompletenessPolicy::class), 'Requires PsySH with StatementCompletenessPolicy');
