<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH;

use drahil\Tailor\Services\AutoSaveService;
use Psy\ExecutionLoop\AbstractListener;
use Psy\Shell;
use RuntimeException;
use Throwable;

class AutoSaveLoopListener extends AbstractListener
{
    private bool $disabled = false;

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
        if ($this->disabled) {
            return;
        }

        try {
            if ($this->autoSaveService->shouldAutoSave()) {
                $this->autoSaveService->performAutoSave();
            }
        } catch (Throwable $e) {
            $this->disabled = true;
            $shell->writeException(new RuntimeException("Auto-save disabled: {$e->getMessage()}", 0, $e));
        }
    }
}
