<?php

declare(strict_types=1);

use drahil\Tailor\PsySH\SessionCommands\SessionAppendCommand;
use drahil\Tailor\Services\HistoryCaptureService;
use drahil\Tailor\Services\SessionTracker;
use drahil\Tailor\Support\DTOs\SessionData;
use drahil\Tailor\Support\DTOs\SessionMetadata;
use drahil\Tailor\Support\ValueObjects\SessionName;
use Psy\Shell;
use Symfony\Component\Console\Input\InputDefinition;

beforeEach(function () {
    $this->sessionManager = mockSessionManager();
    $this->sessionTracker = new SessionTracker();
    $this->sessionTracker->setAppendStartLine(7);
    $this->historyCaptureService = Mockery::mock(HistoryCaptureService::class);

    $this->command = new SessionAppendCommand($this->historyCaptureService);

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

function sessionWithCommands(string $name, int $count): SessionData
{
    $commands = [];
    for ($i = 1; $i <= $count; $i++) {
        $commands[] = ['code' => "\$v{$i} = {$i}", 'output' => null, 'timestamp' => '2025-01-15 10:00:00', 'order' => $i];
    }

    return new SessionData(
        metadata: new SessionMetadata(name: SessionName::from($name)),
        commands: $commands,
        variables: [],
        sessionMetadata: [],
    );
}

test('appends new commands to the active session and moves the marker', function () {
    $this->sessionTracker->setActiveSession('work');
    $this->sessionManager->shouldReceive('exists')->with('work')->andReturn(true);

    $this->historyCaptureService
        ->shouldReceive('captureCommandsSince')
        ->once()
        ->with(Mockery::type('string'), 7)
        ->andReturn(['$c = 3', '$d = 4']);

    $this->sessionManager
        ->shouldReceive('append')
        ->once()
        ->with(Mockery::on(fn ($name) => $name->toString() === 'work'), ['$c = 3', '$d = 4'])
        ->andReturn(sessionWithCommands('work', 6));

    $this->historyCaptureService
        ->shouldReceive('markAppendStart')
        ->once()
        ->with($this->sessionTracker, Mockery::type('string'));

    $this->tester->execute([]);

    expect($this->tester->getStatusCode())->toBe(0)
        ->and($this->tester->getDisplay())->toContain("Appended 2 command(s) to 'work' (6 total)");
});

test('appends to a named session when one is given', function () {
    $this->sessionTracker->setActiveSession('work');
    $this->sessionManager->shouldReceive('exists')->with('other')->andReturn(true);
    $this->historyCaptureService->shouldReceive('captureCommandsSince')->andReturn(['$c = 3']);
    $this->historyCaptureService->shouldReceive('markAppendStart')->once();

    $this->sessionManager
        ->shouldReceive('append')
        ->once()
        ->with(Mockery::on(fn ($name) => $name->toString() === 'other'), ['$c = 3'])
        ->andReturn(sessionWithCommands('other', 3));

    $this->tester->execute(['name' => 'other']);

    expect($this->tester->getDisplay())->toContain("Appended 1 command(s) to 'other' (3 total)");
});

test('fails when no session is active and no name is given', function () {
    $this->sessionManager->shouldNotReceive('append');

    $this->tester->execute([]);

    expect($this->tester->getStatusCode())->toBe(1)
        ->and($this->tester->getDisplay())->toContain('No active session');
});

test('fails when the session does not exist', function () {
    $this->sessionManager->shouldReceive('exists')->with('missing')->andReturn(false);
    $this->sessionManager->shouldNotReceive('append');

    $this->tester->execute(['name' => 'missing']);

    expect($this->tester->getStatusCode())->toBe(1)
        ->and($this->tester->getDisplay())->toContain("Session 'missing' does not exist.");
});

test('does nothing when there are no new commands', function () {
    $this->sessionTracker->setActiveSession('work');
    $this->sessionManager->shouldReceive('exists')->with('work')->andReturn(true);
    $this->historyCaptureService->shouldReceive('captureCommandsSince')->andReturn([]);
    $this->historyCaptureService->shouldNotReceive('markAppendStart');
    $this->sessionManager->shouldNotReceive('append');

    $this->tester->execute([]);

    expect($this->tester->getStatusCode())->toBe(0)
        ->and($this->tester->getDisplay())->toContain('No new commands to append.');
});
