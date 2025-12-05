<?php

declare(strict_types=1);

use drahil\Tailor\PsySH\SessionDeleteCommand;
use Psy\Shell;
use Symfony\Component\Console\Input\InputDefinition;

beforeEach(function () {
    $this->sessionManager = mockSessionManager();
    $this->command = new SessionDeleteCommand();

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

test('deletes session with force flag', function () {
    $this->sessionManager
        ->shouldReceive('exists')
        ->with('test-session')
        ->andReturn(true);

    $this->sessionManager
        ->shouldReceive('delete')
        ->once()
        ->with(Mockery::on(function ($arg) {
            return $arg->toString() === 'test-session';
        }))
        ->andReturn(true);

    $this->tester->execute([
        'name' => 'test-session',
        '--force' => true,
    ]);

    expect($this->tester->getStatusCode())->toBe(0)
        ->and($this->tester->getDisplay())->toContain("Session 'test-session' deleted successfully");
});

test('displays error when session does not exist', function () {
    $this->sessionManager
        ->shouldReceive('exists')
        ->with('nonexistent')
        ->andReturn(false);

    $this->tester->execute([
        'name' => 'nonexistent',
        '--force' => true,
    ]);

    expect($this->tester->getStatusCode())->toBe(1)
        ->and($this->tester->getDisplay())->toContain("Session 'nonexistent' does not exist");
});

test('displays error for invalid session name', function () {
    $this->tester->execute([
        'name' => '',
        '--force' => true,
    ]);

    expect($this->tester->getStatusCode())->toBe(1)
        ->and($this->tester->getDisplay())->toContain('Session name cannot be empty');
});

test('handles delete operation errors gracefully', function () {
    $this->sessionManager
        ->shouldReceive('exists')
        ->andReturn(true);

    $this->sessionManager
        ->shouldReceive('delete')
        ->once()
        ->andThrow(new RuntimeException('Permission denied'));

    $this->tester->execute([
        'name' => 'test-session',
        '--force' => true,
    ]);

    expect($this->tester->getStatusCode())->toBe(1)
        ->and($this->tester->getDisplay())->toContain('Failed to delete session');
});

test('accepts short flag for force option', function () {
    $this->sessionManager
        ->shouldReceive('exists')
        ->andReturn(true);

    $this->sessionManager
        ->shouldReceive('delete')
        ->once()
        ->andReturn(true);

    $this->tester->execute([
        'name' => 'test-session',
        '-f' => true,
    ]);

    expect($this->tester->getStatusCode())->toBe(0)
        ->and($this->tester->getDisplay())->toContain('deleted successfully');
});

test('deletes session with valid name format', function () {
    $validNames = ['simple', 'with-dashes', 'with_underscores', 'session-123'];

    foreach ($validNames as $name) {
        $this->sessionManager
            ->shouldReceive('exists')
            ->with($name)
            ->andReturn(true);

        $this->sessionManager
            ->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $tester = createCommandTester($this->command);
        $tester->execute([
            'name' => $name,
            '--force' => true,
        ]);

        expect($tester->getStatusCode())->toBe(0);
    }
});
