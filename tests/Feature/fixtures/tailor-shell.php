<?php

declare(strict_types=1);

use drahil\Tailor\Services\AutoSaveService;
use drahil\Tailor\Services\SessionManager;
use drahil\Tailor\Services\SessionTracker;
use drahil\Tailor\Services\TailorShellBuilder;
use drahil\Tailor\Support\DTOs\ImportResult;
use drahil\Tailor\Support\Formatting\SessionOutputFormatter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;

require __DIR__ . '/../../bootstrap.php';

Facade::setFacadeApplication(['files' => new Filesystem()]);
config(['tailor.storage.sessions' => getenv('TAILOR_SESSIONS')]);

$tracker = new SessionTracker();
$sessions = new SessionManager();

(new TailorShellBuilder($tracker, $sessions, new AutoSaveService($sessions, $tracker), new SessionOutputFormatter()))
    ->build(ImportResult::empty())
    ->run();
