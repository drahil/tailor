<?php

declare(strict_types=1);

use drahil\Tailor\PsySH\SessionExecuteCommand;
use drahil\Tailor\Support\DTOs\SessionData;
use drahil\Tailor\Support\DTOs\SessionMetadata;
use drahil\Tailor\Support\Formatting\SessionOutputFormatter;
use drahil\Tailor\Support\ValueObjects\SessionName;
use Psy\Shell;
use Symfony\Component\Console\Input\InputDefinition;

beforeEach(function () {
    $this->sessionManager = mockSessionManager();
    $this->formatter = Mockery::mock(SessionOutputFormatter::class);

    $this->command = new SessionExecuteCommand($this->formatter);

    /* Mock the PsySH application */
    $definition = Mockery::mock(InputDefinition::class)->shouldIgnoreMissing();
    $this->app = Mockery::mock(Shell::class)->shouldIgnoreMissing();
    $this->app->shouldReceive('getDefinition')->andReturn($definition);
    $this->app->shouldReceive('getScopeVariable')
        ->with('__sessionManager')
        ->andReturn($this->sessionManager);

    $this->command->setApplication($this->app);
    $this->tester = createCommandTester($this->command);
});

afterEach(function () {
    Mockery::close();
});

test('executes session commands successfully', function () {
    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session'),
        ),
        commands: [
            ['code' => 'echo "test"', 'output' => null, 'timestamp' => '2025-01-15 10:00:00', 'order' => 1],
            ['code' => '$x = 5', 'output' => null, 'timestamp' => '2025-01-15 10:00:01', 'order' => 2],
        ],
        variables: [],
        sessionMetadata: []
    );

    $this->sessionManager
        ->shouldReceive('load')
        ->once()
        ->with(Mockery::on(function ($arg) {
            return $arg->toString() === 'test-session';
        }))
        ->andReturn($sessionData);

    $this->formatter
        ->shouldReceive('displayExecutionHeader')
        ->once();

    $this->app
        ->shouldReceive('execute')
        ->twice()
        ->andReturn(null, 5);

    $this->app
        ->shouldReceive('writeReturnValue')
        ->once()
        ->with(5);

    $this->tester->execute(['name' => 'test-session']);

    expect($this->tester->getStatusCode())->toBe(0)
        ->and($this->tester->getDisplay())->toContain('Executed 2 command(s)');
});

test('skips history marker commands', function () {
    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session'),
        ),
        commands: [
            ['code' => '_HiStOrY_V2_', 'output' => null, 'timestamp' => '2025-01-15 10:00:00', 'order' => 1],
            ['code' => 'echo "test"', 'output' => null, 'timestamp' => '2025-01-15 10:00:01', 'order' => 2],
        ],
        variables: [],
        sessionMetadata: []
    );

    $this->sessionManager
        ->shouldReceive('load')
        ->once()
        ->andReturn($sessionData);

    $this->formatter
        ->shouldReceive('displayExecutionHeader')
        ->once();

    $this->app
        ->shouldReceive('execute')
        ->once()
        ->andReturn(null);

    $this->tester->execute(['name' => 'test-session']);

    expect($this->tester->getStatusCode())->toBe(0)
        ->and($this->tester->getDisplay())->toContain('Executed 1 command(s)');
});

test('handles command execution errors gracefully', function () {
    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session'),
        ),
        commands: [
            ['code' => 'throw new Exception("test")', 'output' => null, 'timestamp' => '2025-01-15 10:00:00', 'order' => 1],
            ['code' => 'echo "ok"', 'output' => null, 'timestamp' => '2025-01-15 10:00:01', 'order' => 2],
        ],
        variables: [],
        sessionMetadata: []
    );

    $this->sessionManager
        ->shouldReceive('load')
        ->once()
        ->andReturn($sessionData);

    $this->formatter
        ->shouldReceive('displayExecutionHeader')
        ->once();

    $this->app
        ->shouldReceive('execute')
        ->twice()
        ->andReturnUsing(function ($code) {
            if (str_contains($code, 'Exception')) {
                throw new Exception('test');
            }
            return null;
        });

    $this->tester->execute(['name' => 'test-session']);

    expect($this->tester->getStatusCode())->toBe(0)
        ->and($this->tester->getDisplay())->toContain('Executed 1 command(s)')
        ->and($this->tester->getDisplay())->toContain('(1 failed)')
        ->and($this->tester->getDisplay())->toContain('Failed to execute');
});

test('displays error when session does not exist', function () {
    $this->sessionManager
        ->shouldReceive('load')
        ->once()
        ->andThrow(new RuntimeException("Session 'nonexistent' does not exist."));

    $this->tester->execute(['name' => 'nonexistent']);

    expect($this->tester->getStatusCode())->toBe(1)
        ->and($this->tester->getDisplay())->toContain('Failed to load session');
});

test('displays error for invalid session name', function () {
    $this->tester->execute(['name' => '']);

    expect($this->tester->getStatusCode())->toBe(1)
        ->and($this->tester->getDisplay())->toContain('Session name cannot be empty');
});

test('decodes URL-encoded commands before execution', function () {
    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session'),
        ),
        commands: [
            ['code' => 'echo+%22hello%22', 'output' => null, 'timestamp' => '2025-01-15 10:00:00', 'order' => 1],
        ],
        variables: [],
        sessionMetadata: []
    );

    $this->sessionManager
        ->shouldReceive('load')
        ->once()
        ->andReturn($sessionData);

    $this->formatter
        ->shouldReceive('displayExecutionHeader')
        ->once();

    $this->app
        ->shouldReceive('execute')
        ->once()
        ->with('echo "hello"')
        ->andReturn(null);

    $this->tester->execute(['name' => 'test-session']);

    expect($this->tester->getStatusCode())->toBe(0);
});

test('displays execution output with command count', function () {
    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session'),
        ),
        commands: [
            ['code' => '$a = 1', 'output' => null, 'timestamp' => '2025-01-15 10:00:00', 'order' => 1],
            ['code' => '$b = 2', 'output' => null, 'timestamp' => '2025-01-15 10:00:01', 'order' => 2],
            ['code' => '$c = 3', 'output' => null, 'timestamp' => '2025-01-15 10:00:02', 'order' => 3],
        ],
        variables: [],
        sessionMetadata: []
    );

    $this->sessionManager
        ->shouldReceive('load')
        ->once()
        ->andReturn($sessionData);

    $this->formatter
        ->shouldReceive('displayExecutionHeader')
        ->once();

    $this->app
        ->shouldReceive('execute')
        ->times(3)
        ->andReturn(null);

    $this->tester->execute(['name' => 'test-session']);

    expect($this->tester->getStatusCode())->toBe(0)
        ->and($this->tester->getDisplay())->toContain('Executed 3 command(s)');
});
