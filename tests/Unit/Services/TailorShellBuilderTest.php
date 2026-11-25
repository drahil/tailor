<?php

declare(strict_types=1);

use drahil\Tailor\PsySH\RecordingReadline;
use drahil\Tailor\Services\AutoSaveService;
use drahil\Tailor\Services\SessionTracker;
use drahil\Tailor\Services\TailorShellBuilder;
use drahil\Tailor\Support\Formatting\SessionOutputFormatter;
use Psy\Configuration;
use Psy\Readline\InteractiveReadline;
use Psy\Readline\Readline;

function shellBuilder(): TailorShellBuilder
{
    return new TailorShellBuilder(
        new SessionTracker(),
        mockSessionManager(),
        Mockery::mock(AutoSaveService::class),
        Mockery::mock(SessionOutputFormatter::class),
    );
}

function shellConfiguration(): Configuration
{
    $builder = shellBuilder();

    return (new ReflectionMethod($builder, 'createConfiguration'))->invoke($builder);
}

function recordingReadlineFor(Configuration $config): ?RecordingReadline
{
    $builder = shellBuilder();

    return (new ReflectionMethod($builder, 'setupRecordingReadline'))->invoke($builder, $config);
}

afterEach(function () {
    config([
        'tailor.autocomplete.enabled' => null,
        'tailor.history.limit' => null,
        'tailor.history.deduplicate' => null,
    ]);
});

test('passes the autocomplete setting to PsySH', function (bool $enabled) {
    config(['tailor.autocomplete.enabled' => $enabled]);

    $reflection = new ReflectionProperty(Configuration::class, 'useTabCompletion');

    expect($reflection->getValue(shellConfiguration()))->toBe($enabled);
})->with([true, false]);

test('passes the history settings to PsySH', function () {
    config(['tailor.history.limit' => 50, 'tailor.history.deduplicate' => false]);

    $config = shellConfiguration();

    expect($config->getHistorySize())->toBe(50)
        ->and($config->getEraseDuplicates())->toBeFalse();
});

test('wraps the readline PsySH would have chosen', function () {
    $config = shellConfiguration();
    $config->setReadline(Mockery::mock(Readline::class));

    $readline = recordingReadlineFor($config);

    expect($config->getReadline())->toBe($readline)
        ->and($readline)->toBeInstanceOf(RecordingReadline::class);
});

test('leaves the experimental interactive readline unwrapped', function () {
    $config = shellConfiguration();
    $config->setReadline($inner = Mockery::mock(InteractiveReadline::class));

    expect(recordingReadlineFor($config))->toBeNull()
        ->and($config->getReadline())->toBe($inner);
});
