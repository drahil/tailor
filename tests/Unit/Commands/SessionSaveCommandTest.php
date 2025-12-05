<?php

declare(strict_types=1);

use drahil\Tailor\PsySH\SessionSaveCommand;
use drahil\Tailor\Services\HistoryCaptureService;
use drahil\Tailor\Support\DTOs\SessionMetadata;
use drahil\Tailor\Support\Formatting\SessionOutputFormatter;
use drahil\Tailor\Support\SessionTracker;
use Psy\Shell;
use Symfony\Component\Console\Input\InputDefinition;

beforeEach(function () {
    $this->sessionManager = mockSessionManager();
    $this->sessionTracker = mockSessionTracker();
    $this->historyCaptureService = Mockery::mock(HistoryCaptureService::class);
    $this->formatter = Mockery::mock(SessionOutputFormatter::class);

    $this->command = new SessionSaveCommand(
        $this->historyCaptureService,
        $this->formatter
    );

    /* Mock the PsySH application */
    $definition = Mockery::mock(InputDefinition::class)->shouldIgnoreMissing();
    $app = Mockery::mock(Shell::class)->shouldIgnoreMissing();
    $app->shouldReceive('getDefinition')->andReturn($definition);
    $app->shouldReceive('getScopeVariable')
        ->with('__sessionManager')
        ->andReturn($this->sessionManager);
    $app->shouldReceive('getScopeVariable')
        ->with('__sessionTracker')
        ->andReturn($this->sessionTracker);

    $this->command->setApplication($app);
    $this->tester = createCommandTester($this->command);
});

afterEach(function () {
    Mockery::close();
});

test('saves session with provided name', function () {
    $this->historyCaptureService
        ->shouldReceive('captureHistoryToTracker')
        ->once();

    $this->sessionTracker
        ->shouldReceive('getCommandCount')
        ->andReturn(5);

    $this->sessionManager
        ->shouldReceive('exists')
        ->andReturn(false);

    $this->sessionManager
        ->shouldReceive('save')
        ->once()
        ->withArgs(function (SessionMetadata $metadata, SessionTracker $tracker) {
            return $metadata->name->toString() === 'my-session'
                && $tracker === $this->sessionTracker;
        });

    $this->formatter
        ->shouldReceive('displaySaveSummary')
        ->once();

    $this->tester->execute(['name' => 'my-session']);

    expect($this->tester->getStatusCode())->toBe(0);
});

test('auto-generates session name when not provided', function () {
    $this->historyCaptureService
        ->shouldReceive('captureHistoryToTracker')
        ->once();

    $this->sessionTracker
        ->shouldReceive('getCommandCount')
        ->andReturn(3);

    $this->sessionManager
        ->shouldReceive('exists')
        ->andReturn(false);

    $this->sessionManager
        ->shouldReceive('save')
        ->once();

    $this->formatter
        ->shouldReceive('displaySaveSummary')
        ->once();

    $this->tester->execute([]);

    expect($this->tester->getStatusCode())->toBe(0)
        ->and($this->tester->getDisplay())->toContain('Auto-generated session name:');
});

test('displays error when no commands to save', function () {
    $this->historyCaptureService
        ->shouldReceive('captureHistoryToTracker')
        ->once();

    $this->sessionTracker
        ->shouldReceive('getCommandCount')
        ->andReturn(0);

    $this->tester->execute(['name' => 'empty-session']);

    expect($this->tester->getStatusCode())->toBe(1)
        ->and($this->tester->getDisplay())->toContain('No commands to save');
});

test('saves session with description and tags', function () {
    $this->historyCaptureService
        ->shouldReceive('captureHistoryToTracker')
        ->once();

    $this->sessionTracker
        ->shouldReceive('getCommandCount')
        ->andReturn(5);

    $this->sessionManager
        ->shouldReceive('exists')
        ->andReturn(false);

    $this->sessionManager
        ->shouldReceive('save')
        ->once()
        ->withArgs(function (SessionMetadata $metadata, SessionTracker $tracker) {
            return $metadata->name->toString() === 'tagged-session'
                && $metadata->description?->toString() === 'Test description'
                && $metadata->tags === ['api', 'testing'];
        });

    $this->formatter
        ->shouldReceive('displaySaveSummary')
        ->once()
        ->withArgs(function ($output, $name, $count, $desc, $tags) {
            return $name === 'tagged-session'
                && $desc === 'Test description'
                && $tags === ['api', 'testing'];
        });

    $this->tester->execute([
        'name' => 'tagged-session',
        '--description' => 'Test description',
        '--tags' => ['api', 'testing'],
    ]);

    expect($this->tester->getStatusCode())->toBe(0);
});

test('overwrites existing session with force flag', function () {
    $this->historyCaptureService
        ->shouldReceive('captureHistoryToTracker')
        ->once();

    $this->sessionTracker
        ->shouldReceive('getCommandCount')
        ->andReturn(3);

    $this->sessionManager
        ->shouldReceive('exists')
        ->with('existing-session')
        ->andReturn(true);

    $this->sessionManager
        ->shouldReceive('save')
        ->once();

    $this->formatter
        ->shouldReceive('displaySaveSummary')
        ->once();

    $this->tester->execute([
        'name' => 'existing-session',
        '--force' => true,
    ]);

    expect($this->tester->getStatusCode())->toBe(0);
});

test('auto-generates name for empty string', function () {
    $this->historyCaptureService
        ->shouldReceive('captureHistoryToTracker')
        ->once();

    $this->sessionTracker
        ->shouldReceive('getCommandCount')
        ->andReturn(5);

    $this->sessionManager
        ->shouldReceive('exists')
        ->andReturn(false);

    $this->sessionManager
        ->shouldReceive('save')
        ->once();

    $this->formatter
        ->shouldReceive('displaySaveSummary')
        ->once();

    $this->tester->execute(['name' => '']);

    expect($this->tester->getStatusCode())->toBe(0)
        ->and($this->tester->getDisplay())->toContain('Auto-generated session name:');
});

test('handles save operation errors gracefully', function () {
    $this->historyCaptureService
        ->shouldReceive('captureHistoryToTracker')
        ->once();

    $this->sessionTracker
        ->shouldReceive('getCommandCount')
        ->andReturn(5);

    $this->sessionManager
        ->shouldReceive('exists')
        ->andReturn(false);

    $this->sessionManager
        ->shouldReceive('save')
        ->once()
        ->andThrow(new RuntimeException('Disk full'));

    $this->tester->execute(['name' => 'test-session']);

    expect($this->tester->getStatusCode())->toBe(1)
        ->and($this->tester->getDisplay())->toContain('Failed to save session');
});
