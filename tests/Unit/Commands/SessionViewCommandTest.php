<?php

declare(strict_types=1);

use drahil\Tailor\PsySH\SessionCommands\SessionViewCommand;
use drahil\Tailor\Support\DTOs\SessionData;
use drahil\Tailor\Support\DTOs\SessionMetadata;
use drahil\Tailor\Support\Formatting\SessionOutputFormatter;
use drahil\Tailor\Support\SessionManager;
use drahil\Tailor\Support\ValueObjects\SessionName;
use Psy\Shell;
use Symfony\Component\Console\Input\InputDefinition;

beforeEach(function () {
    $this->sessionManager = mockSessionManager();
    $this->formatter = Mockery::mock(SessionOutputFormatter::class);

    $this->command = new SessionViewCommand($this->formatter);

    /* Mock the PsySH application */
    $definition = Mockery::mock(InputDefinition::class)->shouldIgnoreMissing();
    $app = Mockery::mock(Shell::class)->shouldIgnoreMissing();
    $app->shouldReceive('getDefinition')->andReturn($definition);
    $app->shouldReceive('getScopeVariable')
        ->with('__sessionManager')
        ->andReturn($this->sessionManager);

    $this->command->setApplication($app);
    $this->tester = createCommandTester($this->command);
});

afterEach(function () {
    Mockery::close();
});

test('displays session details successfully', function () {
    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session'),
        ),
        commands: [
            ['code' => 'echo "test"', 'output' => null, 'timestamp' => '2025-01-15 10:00:00', 'order' => 1],
        ],
        variables: [],
        sessionMetadata: []
    );

    $this->sessionManager
        ->shouldReceive('exists')
        ->with('test-session')
        ->andReturn(true);

    $this->sessionManager
        ->shouldReceive('load')
        ->once()
        ->andReturn($sessionData);

    $this->formatter
        ->shouldReceive('displayMetadata')
        ->once();

    $this->formatter
        ->shouldReceive('displayCommands')
        ->once();

    $this->formatter
        ->shouldReceive('displayVariables')
        ->never();

    $this->tester->execute(['name' => 'test-session']);

    expect($this->tester->getStatusCode())->toBe(0)
        ->and($this->tester->getDisplay())->toContain('Session: test-session');
});

test('displays variables when session has variables', function () {
    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session'),
        ),
        commands: [],
        variables: [
            'foo' => ['type' => 'string', 'class' => null, 'value' => 'bar'],
        ],
        sessionMetadata: []
    );

    $this->sessionManager
        ->shouldReceive('exists')
        ->andReturn(true);

    $this->sessionManager
        ->shouldReceive('load')
        ->andReturn($sessionData);

    $this->formatter
        ->shouldReceive('displayMetadata')
        ->once();

    $this->formatter
        ->shouldReceive('displayCommands')
        ->once();

    $this->formatter
        ->shouldReceive('displayVariables')
        ->once()
        ->with(Mockery::any(), ['foo' => ['type' => 'string', 'class' => null, 'value' => 'bar']]);

    $this->tester->execute(['name' => 'test-session']);

    expect($this->tester->getStatusCode())->toBe(0);
});

test('displays error when session does not exist', function () {
    $this->sessionManager
        ->shouldReceive('exists')
        ->with('nonexistent')
        ->andReturn(false);

    $this->tester->execute(['name' => 'nonexistent']);

    expect($this->tester->getStatusCode())->toBe(1)
        ->and($this->tester->getDisplay())->toContain("Session 'nonexistent' does not exist");
});

test('displays error for invalid session name', function () {
    $this->tester->execute(['name' => '']);

    expect($this->tester->getStatusCode())->toBe(1)
        ->and($this->tester->getDisplay())->toContain('Session name cannot be empty');
});

test('handles load operation errors gracefully', function () {
    $this->sessionManager
        ->shouldReceive('exists')
        ->andReturn(true);

    $this->sessionManager
        ->shouldReceive('load')
        ->once()
        ->andThrow(new RuntimeException('File corrupted'));

    $this->tester->execute(['name' => 'test-session']);

    expect($this->tester->getStatusCode())->toBe(1)
        ->and($this->tester->getDisplay())->toContain('Failed to view session');
});

test('decodes command code correctly when displaying', function () {
    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session'),
        ),
        commands: [
            ['code' => 'echo+%22test%22', 'output' => null, 'timestamp' => '2025-01-15 10:00:00', 'order' => 1],
        ],
        variables: [],
        sessionMetadata: []
    );

    $this->sessionManager
        ->shouldReceive('exists')
        ->andReturn(true);

    $this->sessionManager
        ->shouldReceive('load')
        ->andReturn($sessionData);

    $this->formatter
        ->shouldReceive('displayMetadata')
        ->once();

    $this->formatter
        ->shouldReceive('displayCommands')
        ->once()
        ->withArgs(function ($output, $data, $decoder) use ($sessionData) {
            return $data === $sessionData && is_callable($decoder);
        });

    $this->tester->execute(['name' => 'test-session']);

    expect($this->tester->getStatusCode())->toBe(0);
});
