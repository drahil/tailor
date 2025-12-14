<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH;

use drahil\Tailor\Services\AutoSaveService;
use Psy\ExecutionLoop\AbstractListener;
use Psy\Shell;

class AutoSaveLoopListener extends AbstractListener
{
    public function __construct(
        private readonly AutoSaveService $autoSaveService
    ) {
    }

    public static function isSupported(): bool
    {
        return true;
    }

    public function afterLoop(Shell $shell): void
    {
        if ($this->autoSaveService->shouldAutoSave()) {
            $this->autoSaveService->performAutoSave();
        }
    }
}
