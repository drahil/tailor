<?php

declare(strict_types=1);

use drahil\Tailor\PsySH\SessionUpdateCommand;
use drahil\Tailor\Support\DTOs\SessionData;
use drahil\Tailor\Support\DTOs\SessionMetadata;
use drahil\Tailor\Support\HistoryCaptureService;
use drahil\Tailor\Support\SessionManager;
use drahil\Tailor\Support\SessionTracker;
use drahil\Tailor\Support\ValueObjects\SessionName;
use Psy\Shell;
use Symfony\Component\Console\Input\InputDefinition;

beforeEach(function () {
    $this->sessionManager = mockSessionManager();
    $this->sessionTracker = mockSessionTracker();
    $this->historyCaptureService = Mockery::mock(HistoryCaptureService::class);

    $this->command = new SessionUpdateCommand(
        $this->historyCaptureService
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

test('displays error when no session is loaded', function () {
    $this->sessionTracker
        ->shouldReceive('hasLoadedSession')
        ->once()
        ->andReturn(false);

    $this->tester->execute([]);

    expect($this->tester->getStatusCode())->toBe(1)
        ->and($this->tester->getDisplay())->toContain('No session is currently loaded');
});

test('displays message when no new commands to add', function () {
    $existingCommands = [
        ['code' => '$x = 1', 'output' => null, 'timestamp' => '2025-01-15 10:00:00', 'order' => 1],
        ['code' => '$y = 2', 'output' => null, 'timestamp' => '2025-01-15 10:00:01', 'order' => 2],
    ];

    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session'),
        ),
        commands: $existingCommands,
        variables: [],
        sessionMetadata: []
    );

    $this->sessionTracker
        ->shouldReceive('hasLoadedSession')
        ->andReturn(true);

    $this->sessionTracker
        ->shouldReceive('getLoadedSessionName')
        ->andReturn('test-session');

    $this->sessionManager
        ->shouldReceive('load')
        ->once()
        ->andReturn($sessionData);

    $this->sessionTracker
        ->shouldReceive('getSessionStartLine')
        ->andReturn(100);

    $this->historyCaptureService
        ->shouldReceive('captureFromLine')
        ->once()
        ->with(Mockery::any(), 100)
        ->andReturn([]);

    $this->tester->execute([]);

    expect($this->tester->getStatusCode())->toBe(0)
        ->and($this->tester->getDisplay())->toContain('No new commands to add');
});

test('updates session with new commands successfully', function () {
    $existingCommands = [
        ['code' => '$x = 1', 'output' => null, 'timestamp' => '2025-01-15 10:00:00', 'order' => 1],
        ['code' => '$y = 2', 'output' => null, 'timestamp' => '2025-01-15 10:00:01', 'order' => 2],
    ];

    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session'),
        ),
        commands: $existingCommands,
        variables: [],
        sessionMetadata: []
    );

    $this->sessionTracker
        ->shouldReceive('hasLoadedSession')
        ->andReturn(true);

    $this->sessionTracker
        ->shouldReceive('getLoadedSessionName')
        ->andReturn('test-session');

    $this->sessionManager
        ->shouldReceive('load')
        ->once()
        ->andReturn($sessionData);

    $this->sessionTracker
        ->shouldReceive('getSessionStartLine')
        ->andReturn(100);

    $this->historyCaptureService
        ->shouldReceive('captureFromLine')
        ->once()
        ->with(Mockery::any(), 100)
        ->andReturn(['$z = 3']);

    $this->sessionManager
        ->shouldReceive('saveSessionData')
        ->once()
        ->withArgs(function (SessionData $data) {
            return count($data->commands) === 3
                && $data->metadata->name->toString() === 'test-session';
        });

    $this->tester->execute([]);

    expect($this->tester->getStatusCode())->toBe(0)
        ->and($this->tester->getDisplay())->toContain("Session 'test-session' updated successfully")
        ->and($this->tester->getDisplay())->toContain('Added 1 new command(s)')
        ->and($this->tester->getDisplay())->toContain('Total commands: 3');
});

test('updates session with new description', function () {
    $existingCommands = [
        ['code' => '$x = 1', 'output' => null, 'timestamp' => '2025-01-15 10:00:00', 'order' => 1],
    ];

    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session'),
        ),
        commands: $existingCommands,
        variables: [],
        sessionMetadata: []
    );

    $this->sessionTracker
        ->shouldReceive('hasLoadedSession')
        ->andReturn(true);

    $this->sessionTracker
        ->shouldReceive('getLoadedSessionName')
        ->andReturn('test-session');

    $this->sessionManager
        ->shouldReceive('load')
        ->once()
        ->andReturn($sessionData);

    $this->sessionTracker
        ->shouldReceive('getSessionStartLine')
        ->andReturn(100);

    $this->historyCaptureService
        ->shouldReceive('captureFromLine')
        ->once()
        ->andReturn(['$y = 2']);

    $this->sessionManager
        ->shouldReceive('saveSessionData')
        ->once()
        ->withArgs(function (SessionData $data) {
            return $data->metadata->description?->toString() === 'Updated description';
        });

    $this->tester->execute([
        '--description' => 'Updated description',
    ]);

    expect($this->tester->getStatusCode())->toBe(0);
});

test('updates session with new tags', function () {
    $existingCommands = [
        ['code' => '$x = 1', 'output' => null, 'timestamp' => '2025-01-15 10:00:00', 'order' => 1],
    ];

    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session'),
        ),
        commands: $existingCommands,
        variables: [],
        sessionMetadata: []
    );

    $this->sessionTracker
        ->shouldReceive('hasLoadedSession')
        ->andReturn(true);

    $this->sessionTracker
        ->shouldReceive('getLoadedSessionName')
        ->andReturn('test-session');

    $this->sessionManager
        ->shouldReceive('load')
        ->once()
        ->andReturn($sessionData);

    $this->sessionTracker
        ->shouldReceive('getSessionStartLine')
        ->andReturn(100);

    $this->historyCaptureService
        ->shouldReceive('captureFromLine')
        ->once()
        ->andReturn(['$y = 2']);

    $this->sessionManager
        ->shouldReceive('saveSessionData')
        ->once()
        ->withArgs(function (SessionData $data) {
            return $data->metadata->tags === ['api', 'updated'];
        });

    $this->tester->execute([
        '--tags' => ['api', 'updated'],
    ]);

    expect($this->tester->getStatusCode())->toBe(0);
});

test('handles load operation errors gracefully', function () {
    $this->sessionTracker
        ->shouldReceive('hasLoadedSession')
        ->andReturn(true);

    $this->sessionTracker
        ->shouldReceive('getLoadedSessionName')
        ->andReturn('test-session');

    $this->sessionManager
        ->shouldReceive('load')
        ->once()
        ->andThrow(new RuntimeException('Session file corrupted'));

    $this->tester->execute([]);

    expect($this->tester->getStatusCode())->toBe(1)
        ->and($this->tester->getDisplay())->toContain('Failed to load session');
});

test('handles save operation errors gracefully', function () {
    $existingCommands = [
        ['code' => '$x = 1', 'output' => null, 'timestamp' => '2025-01-15 10:00:00', 'order' => 1],
    ];

    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session'),
        ),
        commands: $existingCommands,
        variables: [],
        sessionMetadata: []
    );

    $this->sessionTracker
        ->shouldReceive('hasLoadedSession')
        ->andReturn(true);

    $this->sessionTracker
        ->shouldReceive('getLoadedSessionName')
        ->andReturn('test-session');

    $this->sessionManager
        ->shouldReceive('load')
        ->once()
        ->andReturn($sessionData);

    $this->sessionTracker
        ->shouldReceive('getSessionStartLine')
        ->andReturn(100);

    $this->historyCaptureService
        ->shouldReceive('captureFromLine')
        ->once()
        ->andReturn(['$y = 2']);

    $this->sessionManager
        ->shouldReceive('saveSessionData')
        ->once()
        ->andThrow(new RuntimeException('Disk full'));

    $this->tester->execute([]);

    expect($this->tester->getStatusCode())->toBe(1)
        ->and($this->tester->getDisplay())->toContain('Failed to update session');
});
