<?php

declare(strict_types=1);

use drahil\Tailor\Services\ClassAutoImporter;
use drahil\Tailor\Services\ClassDiscoveryCache;
use drahil\Tailor\Services\ClassDiscoveryService;
use drahil\Tailor\Services\ClassImportManager;
use drahil\Tailor\Services\IncludeFileManager;
use Symfony\Component\Console\Output\BufferedOutput;

afterEach(function () {
    Mockery::close();
});

test('falls back to no imports when discovery fails', function () {
    $cache = Mockery::mock(ClassDiscoveryCache::class);
    $cache->shouldReceive('getOrDiscover')->andThrow(new Error('Class "Missing" not found'));

    $importer = new ClassAutoImporter(
        $cache,
        Mockery::mock(ClassDiscoveryService::class),
        new ClassImportManager(),
        new IncludeFileManager(),
    );
    $output = new BufferedOutput();

    $result = $importer->prepare($output);

    expect($result->hasIncludeFile())->toBeFalse()
        ->and($output->fetch())->toContain('Auto-import failed: Class "Missing" not found')
        ->toContain('Starting without auto-imported classes.');
});
