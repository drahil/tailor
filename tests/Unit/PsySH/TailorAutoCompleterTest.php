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
