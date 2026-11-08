<?php

declare(strict_types=1);

use drahil\Tailor\Services\CommandFilterService;
use drahil\Tailor\Services\HistoryCaptureService;
use drahil\Tailor\Services\SessionTracker;

beforeEach(function () {
    $this->historyFile = tempnam(sys_get_temp_dir(), 'tailor_history_test_');
    $this->service = new HistoryCaptureService(new CommandFilterService());
    $this->tracker = new SessionTracker();
});

afterEach(function () {
    @unlink($this->historyFile);
});

function capturedCode(SessionTracker $tracker): array
{
    return array_column($tracker->getCommands(), 'code');
}

test('captures readline history lines as-is', function () {
    file_put_contents($this->historyFile, implode("\n", [
        '$a = 1+1',
        'App\Models\User::first()',
    ]) . "\n");

    $this->service->captureHistoryToTracker($this->tracker, $this->historyFile);

    expect(capturedCode($this->tracker))->toBe([
        '$a = 1+1',
        'App\Models\User::first()',
    ]);
});

test('decodes libedit history and skips its header', function () {
    file_put_contents($this->historyFile, implode("\n", [
        '_HiStOrY_V2_',
        '$a\040=\0401+1',
        'App\\\\Models\\\\User::first()',
    ]) . "\n");

    $this->service->captureHistoryToTracker($this->tracker, $this->historyFile);

    expect(capturedCode($this->tracker))->toBe([
        '$a = 1+1',
        'App\Models\User::first()',
    ]);
});

test('captures only lines after the session start in libedit history', function () {
    file_put_contents($this->historyFile, implode("\n", [
        '_HiStOrY_V2_',
        '$old\040=\0401',
    ]) . "\n");

    $this->service->markSessionStart($this->tracker, $this->historyFile);
    file_put_contents($this->historyFile, '$new\040=\0402' . "\n", FILE_APPEND);

    $this->service->captureHistoryToTracker($this->tracker, $this->historyFile);

    expect(capturedCode($this->tracker))->toBe(['$new = 2']);
});

test('captureCommandsSince returns commands after the given line', function () {
    file_put_contents($this->historyFile, implode("\n", ['$a = 1', 'session:execute work', '$b = 2', 'append']) . "\n");

    expect($this->service->captureCommandsSince($this->historyFile, 1))->toBe(['$b = 2']);
});

test('markAppendStart moves the append marker to the end of history', function () {
    file_put_contents($this->historyFile, implode("\n", ['$a = 1', '$b = 2']) . "\n");

    $this->service->markAppendStart($this->tracker, $this->historyFile);

    expect($this->tracker->getAppendStartLine())->toBe(2);
});

test('markSessionStart sets both the session and append markers', function () {
    file_put_contents($this->historyFile, implode("\n", ['$a = 1', '$b = 2', '$c = 3']) . "\n");

    $this->service->markSessionStart($this->tracker, $this->historyFile);

    expect($this->tracker->getSessionStartLine())->toBe(3)
        ->and($this->tracker->getAppendStartLine())->toBe(3);
});
