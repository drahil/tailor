<?php

declare(strict_types=1);

use drahil\Tailor\Services\SessionTracker;

test('clear removes commands', function () {
    $tracker = new SessionTracker();
    $tracker->addCommand('$a = 1');

    $tracker->clear();

    expect($tracker->getCommandCount())->toBe(0)
        ->and($tracker->hasCommands())->toBeFalse();
});
