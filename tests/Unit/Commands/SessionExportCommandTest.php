<?php

declare(strict_types=1);

use drahil\Tailor\PsySH\SessionCommands\SessionExportCommand;
use drahil\Tailor\Services\SessionExporter;
use drahil\Tailor\Support\DTOs\SessionData;
use drahil\Tailor\Support\DTOs\SessionMetadata;
use drahil\Tailor\Support\ValueObjects\SessionName;
use Illuminate\Filesystem\Filesystem;
use Psy\Shell;
use Symfony\Component\Console\Input\InputDefinition;

beforeEach(function () {
    $this->directory = sys_get_temp_dir() . '/tailor-export-' . uniqid();
    $this->sessionManager = mockSessionManager();
    $this->sessionManager->shouldReceive('exists')->with('blog-stats')->andReturn(true)->byDefault();
    $this->sessionManager->shouldReceive('load')->andReturn(new SessionData(
        metadata: new SessionMetadata(name: SessionName::from('blog-stats')),
        commands: ['NoSuchThing::count();'],
    ))->byDefault();

    $this->command = new SessionExportCommand(new SessionExporter());

    $definition = Mockery::mock(InputDefinition::class)->shouldIgnoreMissing();
    $app = Mockery::mock(Shell::class)->shouldIgnoreMissing();
    $app->shouldReceive('getDefinition')->andReturn($definition);
    $app->shouldReceive('getScopeVariable')->with('__sessionManager')->andReturn($this->sessionManager);

    $this->command->setApplication($app);
    $this->tester = createCommandTester($this->command);
});

afterEach(function () {
    (new Filesystem())->deleteDirectory($this->directory);
    Mockery::close();
});

test('exports a command to the configured folder by default, in the namespace of that folder', function () {
    $this->directory = base_path('tests/ExportOutput' . uniqid());
    config(['tailor.export.path' => $this->directory]);

    $this->tester->execute(['name' => 'blog-stats']);
    config(['tailor.export.path' => null]);

    expect($this->tester->getStatusCode())->toBe(0)
        ->and(file_get_contents("{$this->directory}/BlogStats.php"))->toContain('class BlogStats extends Command')
        ->toContain('namespace drahil\\Tailor\\Tests\\' . basename($this->directory) . ';')
        ->and($this->tester->getDisplay())->toContain('php artisan tailor:blog-stats')
        ->not->toContain('PSR-4');
});

test('warns about unresolved classes and folders Composer does not autoload', function () {
    $this->tester->execute(['name' => 'blog-stats', '--path' => $this->directory]);

    expect($this->tester->getStatusCode())->toBe(0)
        ->and(file_get_contents("{$this->directory}/BlogStats.php"))->toContain('namespace App\\Console\\Commands;')
        ->and($this->tester->getDisplay())->toContain("Could not resolve class 'NoSuchThing'")
        ->toContain('is not in a PSR-4 autoloaded folder')
        ->not->toContain('Run it with');
});

test('does not overwrite an existing file without --force', function () {
    mkdir($this->directory);
    file_put_contents("{$this->directory}/BlogStats.php", 'original');

    $this->tester->execute(['name' => 'blog-stats', '--path' => $this->directory]);

    expect($this->tester->getStatusCode())->toBe(1)
        ->and($this->tester->getDisplay())->toContain('already exists. Use --force')
        ->and(file_get_contents("{$this->directory}/BlogStats.php"))->toBe('original');
});

test('overwrites an existing file with --force', function () {
    mkdir($this->directory);
    file_put_contents("{$this->directory}/BlogStats.php", 'original');

    $this->tester->execute(['name' => 'blog-stats', '--path' => $this->directory, '--force' => true]);

    expect($this->tester->getStatusCode())->toBe(0)
        ->and(file_get_contents("{$this->directory}/BlogStats.php"))->toContain('NoSuchThing::count()');
});

test('fails when the session does not exist', function () {
    $this->sessionManager->shouldReceive('exists')->with('missing')->andReturn(false);

    $this->tester->execute(['name' => 'missing', '--path' => $this->directory]);

    expect($this->tester->getStatusCode())->toBe(1)
        ->and($this->tester->getDisplay())->toContain("Session 'missing' does not exist.");
});
