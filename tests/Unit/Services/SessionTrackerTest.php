<?php

declare(strict_types=1);

use drahil\Tailor\Services\SessionTracker;

test('collects commands for append from the last marker on', function () {
    $tracker = new SessionTracker();
    $tracker->addCommand('$a = 1');
    $tracker->markAppendStart();
    $tracker->addCommand('$b = 2');

    expect($tracker->getCommandsSinceAppendStart())->toBe(['$b = 2'])
        ->and($tracker->getCommands())->toBe(['$a = 1', '$b = 2']);
});

test('collects every command before the first marker', function () {
    $tracker = new SessionTracker();
    $tracker->addCommand('$a = 1');

    expect($tracker->getCommandsSinceAppendStart())->toBe(['$a = 1']);
});
