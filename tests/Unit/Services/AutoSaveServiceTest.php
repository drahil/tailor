<?php

declare(strict_types=1);

use drahil\Tailor\Services\AutoSaveService;
use drahil\Tailor\Services\HistoryCaptureService;
use drahil\Tailor\Services\SessionManager;
use drahil\Tailor\Services\SessionTracker;
use drahil\Tailor\Support\DTOs\SessionMetadata;

beforeEach(function () {
    $this->sessionManager = mockSessionManager();
    $this->sessionTracker = mockSessionTracker();
    $this->historyCaptureService = Mockery::mock(HistoryCaptureService::class);

    $this->autoSaveService = new AutoSaveService(
        $this->sessionManager,
        $this->sessionTracker,
        $this->historyCaptureService
    );
});

afterEach(function () {
    Mockery::close();
});

test('shouldAutoSave returns false when auto_save is disabled', function () {
    config(['tailor.session.auto_save' => false]);

    expect($this->autoSaveService->shouldAutoSave())->toBeFalse();
});

test('shouldAutoSave returns true when command count exceeds threshold', function () {
    config([
        'tailor.session.auto_save' => true,
        'tailor.session.auto_save_min_commands' => 5,
        'tailor.session.auto_save_interval' => 300,
    ]);

    $this->historyCaptureService
        ->shouldReceive('captureHistoryToTracker')
        ->once()
        ->with($this->sessionTracker, Mockery::type('string'));

    $this->sessionTracker
        ->shouldReceive('getCommandCount')
        ->andReturn(6);

    $this->sessionTracker
        ->shouldReceive('getDuration')
        ->andReturn(10);

    expect($this->autoSaveService->shouldAutoSave())->toBeTrue();
});

test('shouldAutoSave returns true when duration exceeds threshold', function () {
    config([
        'tailor.session.auto_save' => true,
        'tailor.session.auto_save_min_commands' => 5,
        'tailor.session.auto_save_interval' => 300,
    ]);

    $this->historyCaptureService
        ->shouldReceive('captureHistoryToTracker')
        ->once()
        ->with($this->sessionTracker, Mockery::type('string'));

    $this->sessionTracker
        ->shouldReceive('getCommandCount')
        ->andReturn(2);

    $this->sessionTracker
        ->shouldReceive('getDuration')
        ->andReturn(350);

    expect($this->autoSaveService->shouldAutoSave())->toBeTrue();
});

test('shouldAutoSave returns false when neither threshold is met', function () {
    config([
        'tailor.session.auto_save' => true,
        'tailor.session.auto_save_min_commands' => 5,
        'tailor.session.auto_save_interval' => 300,
    ]);

    $this->historyCaptureService
        ->shouldReceive('captureHistoryToTracker')
        ->once()
        ->with($this->sessionTracker, Mockery::type('string'));

    $this->sessionTracker
        ->shouldReceive('getCommandCount')
        ->andReturn(3);

    $this->sessionTracker
        ->shouldReceive('getDuration')
        ->andReturn(100);

    expect($this->autoSaveService->shouldAutoSave())->toBeFalse();
});

test('performAutoSave captures history and saves session', function () {
    $this->sessionTracker
        ->shouldReceive('hasCommands')
        ->andReturn(true);

    $this->sessionManager
        ->shouldReceive('save')
        ->once()
        ->withArgs(function (SessionMetadata $metadata, SessionTracker $tracker) {
            return str_starts_with($metadata->name->toString(), 'session-auto-saved-')
                && $metadata->description->toString() === 'Auto-saved session'
                && in_array('auto-saved', $metadata->tags)
                && $tracker === $this->sessionTracker;
        });

    $this->autoSaveService->performAutoSave();

    expect($this->autoSaveService->hasAutoSaved())->toBeTrue()
        ->and($this->autoSaveService->getAutoSavedSessionName())->toStartWith('session-auto-saved-');
});

test('performAutoSave does not save when no commands exist', function () {
    $this->sessionTracker
        ->shouldReceive('hasCommands')
        ->andReturn(false);

    $this->sessionManager
        ->shouldNotReceive('save');

    $this->autoSaveService->performAutoSave();

    expect($this->autoSaveService->hasAutoSaved())->toBeFalse();
});

test('performAutoSave reuses same session name on subsequent saves', function () {
    $this->sessionTracker
        ->shouldReceive('hasCommands')
        ->twice()
        ->andReturn(true);

    $capturedSessionNames = [];

    $this->sessionManager
        ->shouldReceive('save')
        ->twice()
        ->withArgs(function (SessionMetadata $metadata, SessionTracker $tracker) use (&$capturedSessionNames) {
            $capturedSessionNames[] = $metadata->name->toString();
            return true;
        });

    $this->autoSaveService->performAutoSave();
    $firstSessionName = $this->autoSaveService->getAutoSavedSessionName();

    $this->autoSaveService->performAutoSave();
    $secondSessionName = $this->autoSaveService->getAutoSavedSessionName();

    expect($firstSessionName)->toBe($secondSessionName)
        ->and($capturedSessionNames[0])->toBe($capturedSessionNames[1]);
});
