<?php

declare(strict_types=1);

use drahil\Tailor\PsySH\RecordingReadline;
use drahil\Tailor\Services\SessionTracker;
use Psy\Readline\Readline;
use Psy\Readline\Transient;

function recordingReadline(SessionTracker $tracker, ?Readline $inner = null): RecordingReadline
{
    $readline = RecordingReadline::wrap($inner ?? new Transient(), $tracker);
    $readline->setCommandNames(['session:save', 'save', 'ls', 'doc', 'exit']);

    return $readline;
}

test('records code entries and keeps them in the shared history', function () {
    $tracker = new SessionTracker();
    $readline = recordingReadline($tracker);

    $readline->addHistory('$a = 1');
    $readline->addHistory("foreach (\$xs as \$x) {\n    echo \$x;\n}");

    expect($tracker->getCommands())->toBe(['$a = 1', "foreach (\$xs as \$x) {\n    echo \$x;\n}"])
        ->and($readline->listHistory())->toHaveCount(2);
});

test('skips shell commands but keeps them in the shared history', function () {
    $tracker = new SessionTracker();
    $readline = recordingReadline($tracker);

    foreach (['session:save work', 'save', 'ls -la', 'doc User', 'exit'] as $line) {
        $readline->addHistory($line);
    }

    expect($tracker->getCommands())->toBe([])
        ->and($readline->listHistory())->toHaveCount(5);
});

test('records code that starts like a command name, as PsySH runs it as code', function () {
    $tracker = new SessionTracker();

    recordingReadline($tracker)->addHistory('doc($user)');

    expect($tracker->getCommands())->toBe(['doc($user)']);
});

test('forwards every call to the wrapped readline', function () {
    $inner = Mockery::mock(Readline::class);
    $inner->shouldReceive('addHistory')->once()->with('$a = 1')->andReturn(true);
    $inner->shouldReceive('clearHistory')->once()->andReturn(true);
    $inner->shouldReceive('listHistory')->once()->andReturn(['$a = 1']);
    $inner->shouldReceive('readHistory')->once()->andReturn(true);
    $inner->shouldReceive('readline')->once()->with('> ')->andReturn('$b = 2');
    $inner->shouldReceive('redisplay')->once();
    $inner->shouldReceive('writeHistory')->once()->andReturn(true);

    $readline = recordingReadline(new SessionTracker(), $inner);

    expect($readline->addHistory('$a = 1'))->toBeTrue()
        ->and($readline->clearHistory())->toBeTrue()
        ->and($readline->listHistory())->toBe(['$a = 1'])
        ->and($readline->readHistory())->toBeTrue()
        ->and($readline->readline('> '))->toBe('$b = 2')
        ->and($readline->writeHistory())->toBeTrue();

    $readline->redisplay();
});

test('keeps the commands of two shells apart', function () {
    $first = new SessionTracker();
    $second = new SessionTracker();
    $history = new Transient();

    recordingReadline($first, $history)->addHistory('$a = 1');
    recordingReadline($second, $history)->addHistory('$b = 2');

    expect($first->getCommands())->toBe(['$a = 1'])
        ->and($second->getCommands())->toBe(['$b = 2']);
});
