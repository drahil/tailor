<?php

declare(strict_types=1);

use drahil\Tailor\PsySH\SessionCommands\SessionRenameCommand;
use drahil\Tailor\Services\SessionTracker;
use Psy\Shell;
use Symfony\Component\Console\Input\InputDefinition;

beforeEach(function () {
    $this->sessionManager = mockSessionManager();
    $this->sessionManager->shouldReceive('exists')->with('old')->andReturn(true)->byDefault();
    $this->sessionManager->shouldReceive('exists')->with('new')->andReturn(false)->byDefault();
    $this->sessionTracker = new SessionTracker();

    $this->command = new SessionRenameCommand();

    $definition = Mockery::mock(InputDefinition::class)->shouldIgnoreMissing();
    $app = Mockery::mock(Shell::class)->shouldIgnoreMissing();
    $app->shouldReceive('getDefinition')->andReturn($definition);
    $app->shouldReceive('getScopeVariable')->with('__sessionManager')->andReturn($this->sessionManager);
    $app->shouldReceive('getScopeVariable')->with('__sessionTracker')->andReturn($this->sessionTracker);

    $this->command->setApplication($app);
    $this->tester = createCommandTester($this->command);
});

afterEach(function () {
    Mockery::close();
});

function renamesTo(string $from, string $to): array
{
    return [
        Mockery::on(fn ($name) => $name->toString() === $from),
        Mockery::on(fn ($name) => $name->toString() === $to),
    ];
}

test('renames a session', function () {
    $this->sessionManager->shouldReceive('rename')->once()->with(...renamesTo('old', 'new'));

    $this->tester->execute(['name' => 'old', 'new-name' => 'new']);

    expect($this->tester->getStatusCode())->toBe(0)
        ->and($this->tester->getDisplay())->toContain("Renamed 'old' to 'new'");
});

test('keeps the active session for session:append after a rename', function () {
    $this->sessionTracker->setActiveSession('old');
    $this->sessionManager->shouldReceive('rename')->once();

    $this->tester->execute(['name' => 'old', 'new-name' => 'new']);

    expect($this->sessionTracker->getActiveSession())->toBe('new');
});

test('leaves another active session alone', function () {
    $this->sessionTracker->setActiveSession('work');
    $this->sessionManager->shouldReceive('rename')->once();

    $this->tester->execute(['name' => 'old', 'new-name' => 'new']);

    expect($this->sessionTracker->getActiveSession())->toBe('work');
});

test('asks before replacing an existing session and cancels when declined', function () {
    $this->sessionManager->shouldReceive('exists')->with('new')->andReturn(true);
    $this->sessionManager->shouldNotReceive('rename');

    $this->tester->setInputs(['n']);
    $this->tester->execute(['name' => 'old', 'new-name' => 'new']);

    expect($this->tester->getDisplay())->toContain("Session 'new' already exists.")
        ->toContain("Replace 'new' with 'old'? [y/N]")
        ->toContain('Rename cancelled.');
});

test('replaces an existing session when confirmed', function () {
    $this->sessionManager->shouldReceive('exists')->with('new')->andReturn(true);
    $this->sessionManager->shouldReceive('rename')->once()->with(...renamesTo('old', 'new'));

    $this->tester->setInputs(['y']);
    $this->tester->execute(['name' => 'old', 'new-name' => 'new']);

    expect($this->tester->getDisplay())->toContain("Renamed 'old' to 'new'");
});

test('replaces an existing session without asking when forced', function () {
    $this->sessionManager->shouldReceive('exists')->with('new')->andReturn(true);
    $this->sessionManager->shouldReceive('rename')->once();

    $this->tester->execute(['name' => 'old', 'new-name' => 'new', '--force' => true]);

    expect($this->tester->getDisplay())->not->toContain('Replace')
        ->toContain("Renamed 'old' to 'new'");
});

test('does nothing when the name does not change', function () {
    $this->sessionManager->shouldNotReceive('rename');

    $this->tester->execute(['name' => 'old', 'new-name' => 'OLD']);

    expect($this->tester->getStatusCode())->toBe(0)
        ->and($this->tester->getDisplay())->toContain("Session 'old' already has that name.");
});

test('fails when the session does not exist', function () {
    $this->sessionManager->shouldReceive('exists')->with('missing')->andReturn(false);
    $this->sessionManager->shouldNotReceive('rename');

    $this->tester->execute(['name' => 'missing', 'new-name' => 'new']);

    expect($this->tester->getStatusCode())->toBe(1)
        ->and($this->tester->getDisplay())->toContain("Session 'missing' does not exist.");
});

test('rejects an invalid new name', function () {
    $this->sessionManager->shouldNotReceive('rename');

    $this->tester->execute(['name' => 'old', 'new-name' => 'bad name!']);

    expect($this->tester->getStatusCode())->toBe(1)
        ->and($this->tester->getDisplay())->toContain('Session name must contain only');
});

test('reports a failed rename', function () {
    $this->sessionManager->shouldReceive('rename')->andThrow(new RuntimeException('disk full'));

    $this->tester->execute(['name' => 'old', 'new-name' => 'new']);

    expect($this->tester->getStatusCode())->toBe(1)
        ->and($this->tester->getDisplay())->toContain('Failed to rename session: disk full');
});
