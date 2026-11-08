<?php

declare(strict_types=1);

use drahil\Tailor\Services\SessionTracker;

test('clear removes commands but keeps the session start time', function () {
    $tracker = new SessionTracker();
    $startedAt = $tracker->getStartedAt();
    $tracker->addCommand('$a = 1');

    $tracker->clear();

    expect($tracker->getCommandCount())->toBe(0)
        ->and($tracker->getStartedAt())->toBe($startedAt);
});
