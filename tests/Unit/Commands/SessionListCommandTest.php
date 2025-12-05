<?php

declare(strict_types=1);

use drahil\Tailor\PsySH\SessionListCommand;
use drahil\Tailor\Support\SessionManager;
use Psy\Shell;
use Symfony\Component\Console\Input\InputDefinition;

beforeEach(function () {
    $this->sessionManager = mockSessionManager();
    $this->command = new SessionListCommand();

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

test('displays message when no sessions exist', function () {
    $this->sessionManager
        ->shouldReceive('list')
        ->once()
        ->andReturn([]);

    $this->tester->execute([]);

    expect($this->tester->getStatusCode())->toBe(0)
        ->and($this->tester->getDisplay())->toContain('No saved sessions found.');
});

test('lists sessions with metadata', function () {
    $sessions = [
        [
            'name' => 'test-session',
            'created_at' => '2025-01-15 10:00:00',
            'command_count' => 5,
        ],
        [
            'name' => 'another-session',
            'created_at' => '2025-01-16 14:30:00',
            'command_count' => 10,
        ],
    ];

    $this->sessionManager
        ->shouldReceive('list')
        ->once()
        ->andReturn($sessions);

    $this->tester->execute([]);

    expect($this->tester->getStatusCode())->toBe(0);

    $output = $this->tester->getDisplay();
    expect($output)->toContain('Saved Sessions:')
        ->and($output)->toContain('test-session')
        ->and($output)->toContain('2025-01-15 10:00:00')
        ->and($output)->toContain('5 commands')
        ->and($output)->toContain('another-session')
        ->and($output)->toContain('2025-01-16 14:30:00')
        ->and($output)->toContain('10 commands');
});

test('formats output correctly for single session', function () {
    $sessions = [
        [
            'name' => 'my-session',
            'created_at' => '2025-01-15 10:00:00',
            'command_count' => 3,
        ],
    ];

    $this->sessionManager
        ->shouldReceive('list')
        ->once()
        ->andReturn($sessions);

    $this->tester->execute([]);

    expect($this->tester->getStatusCode())->toBe(0)
        ->and($this->tester->getDisplay())->toContain('my-session')
        ->and($this->tester->getDisplay())->toContain('3 commands');
});
