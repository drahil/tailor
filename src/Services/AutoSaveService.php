<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

use drahil\Tailor\Support\DTOs\SessionMetadata;
use drahil\Tailor\Support\ValueObjects\SessionDescription;
use drahil\Tailor\Support\ValueObjects\SessionName;
use DateTime;

class AutoSaveService
{
    protected ?string $autoSavedSessionName = null;

    protected bool $hasAutoSaved = false;

    protected int $lastSavedCommandCount = 0;

    protected int $lastSavedAt;

    public function __construct(
        protected SessionManager $sessionManager,
        protected SessionTracker $sessionTracker,
        protected HistoryCaptureService $historyCaptureService,
    ) {
        $this->lastSavedAt = time();
    }

    public function shouldAutoSave(): bool
    {
        if (! config('tailor.session.auto_save', false)) {
            return false;
        }

        $minCommands = config('tailor.session.auto_save_min_commands', 5);
        $maxInterval = config('tailor.session.auto_save_interval', 300);

        $historyFile = storage_path('tailor/tailor_history');
        $this->historyCaptureService->captureHistoryToTracker($this->sessionTracker, $historyFile);

        $unsavedCommands = $this->sessionTracker->getCommandCount() - $this->lastSavedCommandCount;

        if ($unsavedCommands <= 0) {
            return false;
        }

        return $unsavedCommands >= $minCommands || time() - $this->lastSavedAt >= $maxInterval;
    }

    public function performAutoSave(): void
    {
        if (! $this->sessionTracker->hasCommands()) {
            return;
        }

        if ($this->autoSavedSessionName === null) {
            $this->autoSavedSessionName = $this->generateAutoSaveName();
        }

        $metadata = new SessionMetadata(
            name: SessionName::from($this->autoSavedSessionName),
            description: SessionDescription::from('Auto-saved session'),
            tags: ['auto-saved'],
            createdAt: new DateTime(),
            updatedAt: new DateTime(),
            laravelVersion: app()->version(),
            phpVersion: PHP_VERSION,
        );

        $this->sessionManager->save($metadata, $this->sessionTracker);
        $this->hasAutoSaved = true;
        $this->lastSavedCommandCount = $this->sessionTracker->getCommandCount();
        $this->lastSavedAt = time();
    }

    public function hasAutoSaved(): bool
    {
        return $this->hasAutoSaved;
    }

    public function getAutoSavedSessionName(): ?string
    {
        return $this->autoSavedSessionName;
    }

    protected function generateAutoSaveName(): string
    {
        return 'session-auto-saved-' . date('Y-m-d-His');
    }
}
