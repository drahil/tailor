<?php

declare(strict_types=1);

use drahil\Tailor\Services\AutoSaveService;
use drahil\Tailor\Services\HistoryCaptureService;
use drahil\Tailor\Services\SessionTracker;
use drahil\Tailor\Services\TailorShellBuilder;
use drahil\Tailor\Support\Formatting\SessionOutputFormatter;
use Psy\Configuration;

function shellConfiguration(): Configuration
{
    $builder = new TailorShellBuilder(
        new SessionTracker(),
        mockSessionManager(),
        Mockery::mock(AutoSaveService::class),
        Mockery::mock(HistoryCaptureService::class),
        Mockery::mock(SessionOutputFormatter::class),
    );

    return (new ReflectionMethod($builder, 'createConfiguration'))->invoke($builder);
}

afterEach(function () {
    config(['tailor.autocomplete.enabled' => null]);
});

test('passes the autocomplete setting to PsySH', function (bool $enabled) {
    config(['tailor.autocomplete.enabled' => $enabled]);

    $reflection = new ReflectionProperty(Configuration::class, 'useTabCompletion');

    expect($reflection->getValue(shellConfiguration()))->toBe($enabled);
})->with([true, false]);
