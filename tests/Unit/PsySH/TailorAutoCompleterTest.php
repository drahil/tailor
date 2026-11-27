<?php

declare(strict_types=1);

use drahil\Tailor\PsySH\TailorAutoCompleter;
use Psy\TabCompletion\Matcher\ClassMethodsMatcher;
use Psy\TabCompletion\Matcher\CommandsMatcher;
use Psy\TabCompletion\Matcher\FunctionDefaultParametersMatcher;
use Psy\TabCompletion\Matcher\ObjectAttributesMatcher;

function registeredMatchers(TailorAutoCompleter $completer): array
{
    return (new ReflectionProperty($completer, 'matchers'))->getValue($completer);
}

test('keeps default matchers that Tailor does not replace', function () {
    $completer = new TailorAutoCompleter();
    $commands = new CommandsMatcher([]);
    $defaultParams = new FunctionDefaultParametersMatcher();

    $completer->addMatcher($commands);
    $completer->addMatcher($defaultParams);

    expect(registeredMatchers($completer))->toBe([$commands, $defaultParams]);
});

test('drops default matchers that Tailor replaces with filtered versions', function () {
    $completer = new TailorAutoCompleter();

    $completer->addMatcher(new ClassMethodsMatcher());
    $completer->addMatcher(new ObjectAttributesMatcher());

    expect(registeredMatchers($completer))->toBe([]);
});

test('completes namespaced command names at the start of the line', function () {
    $completer = new TailorAutoCompleter();
    $completer->setCommandNames(['session:ls', 'session:execute', 'session:edit', 'help']);

    expect($completer->processCallback('session:e', 0, ['line_buffer' => 'session:e', 'end' => 9]))
        ->toBe(['session:execute', 'session:edit']);
});

test('doubles a single command match so readline adds no trailing space', function () {
    $completer = new TailorAutoCompleter();
    $completer->setCommandNames(['session:ls', 'session:execute']);

    expect($completer->processCallback('session:ex', 0, ['line_buffer' => 'session:ex', 'end' => 10]))
        ->toBe(['session:execute', 'session:execute']);
});
