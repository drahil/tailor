<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

use drahil\Tailor\Support\DTOs\SessionData;
use drahil\Tailor\Support\DTOs\SessionMetadata;
use drahil\Tailor\Support\ValueObjects\SessionName;
use Exception;
use Illuminate\Contracts\Container\Singleton;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

/**
 * Manages session storage and retrieval.
 *
 * Handles saving, loading, listing, and deleting sessions
 * using a file-based storage system.
 */
#[Singleton]
class SessionManager
{
    /**
     * Path to the sessions storage directory.
     */
    protected string $storagePath;

    /**
     * Create a new SessionManager instance.
     */
    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? config('tailor.storage.sessions', storage_path('tailor/sessions'));
        $this->ensureStorageDirectoryExists();
    }

    /**
     * Check if a session exists.
     */
    public function exists(SessionName|string $name): bool
    {
        $sessionName = $name instanceof SessionName ? $name : SessionName::from($name);
        return File::exists($this->getSessionPath($sessionName));
    }

    /**
     * Save a session with metadata and tracker data.
     *
     * @throws RuntimeException|FileNotFoundException
     */
    public function save(SessionMetadata $metadata, SessionTracker $tracker): void
    {
        $sessionPath = $this->getSessionPath($metadata->name);

        $existingCreatedAt = null;
        if ($this->exists($metadata->name)) {
            $existing = $this->load($metadata->name);
            $existingCreatedAt = $existing->metadata->createdAt;
        }

        $sessionData = SessionData::fromTracker(
            metadata: new SessionMetadata(
                name: $metadata->name,
                description: $metadata->description,
                tags: $metadata->tags,
                createdAt: $existingCreatedAt ?? $metadata->createdAt,
                updatedAt: $metadata->updatedAt,
                laravelVersion: app()->version(),
                phpVersion: PHP_VERSION,
            ),
            tracker: $tracker
        );

        $json = json_encode($sessionData->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Failed to encode session data to JSON: ' . json_last_error_msg());
        }

        if (File::put($sessionPath, $json) === false) {
            throw new RuntimeException("Failed to write session to file: {$sessionPath}");
        }
    }

    /**
     * Load a session by name.
     *
     * @throws RuntimeException|FileNotFoundException|Exception
     */
    public function load(SessionName|string $name): SessionData
    {
        $sessionName = $name instanceof SessionName ? $name : SessionName::from($name);

        if (! $this->exists($sessionName)) {
            throw new RuntimeException("Session '{$sessionName}' does not exist.");
        }

        $sessionPath = $this->getSessionPath($sessionName);
        $json = File::get($sessionPath);

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                "Failed to decode session '{$sessionName}': " . json_last_error_msg()
            );
        }

        return SessionData::fromArray($data);
    }

    /**
     * List all available sessions with optional tag filtering.
     *
     * @param array<string> $filterTags If provided, only sessions containing ALL these tags
     * @return array<int, array{
     *     name: string,
     *     description: string|null,
     *     tags: array<int, string>,
     *     created_at: string|null,
     *     updated_at: string|null,
     *     command_count: int,
     *     laravel_version: string|null}>
     */
    public function list(array $filterTags = []): array
    {
        $this->ensureStorageDirectoryExists();

        $files = File::glob($this->storagePath . '/*.json');
        $sessions = [];

        foreach ($files as $file) {
            try {
                $sessionData = json_decode(File::get($file), true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $sessionTags = $sessionData['tags'] ?? [];

                    if (! empty($filterTags) && ! $this->hasAllTags($sessionTags, $filterTags)) {
                        continue;
                    }

                    $sessions[] = [
                        'name' => $sessionData['name'] ?? basename($file, '.json'),
                        'description' => $sessionData['description'] ?? null,
                        'tags' => $sessionTags,
                        'created_at' => $sessionData['created_at'] ?? null,
                        'updated_at' => $sessionData['updated_at'] ?? null,
                        'command_count' => $sessionData['metadata']['total_commands'] ?? count($sessionData['commands'] ?? []),
                        'laravel_version' => $sessionData['laravel_version'] ?? null,
                    ];
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        usort($sessions, function ($a, $b) {
            return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
        });

        return $sessions;
    }

    /**
     * Check if a session has all specified tags.
     *
     * @param array<string> $sessionTags
     * @param array<string> $filterTags
     */
    private function hasAllTags(array $sessionTags, array $filterTags): bool
    {
        return empty(array_diff($filterTags, $sessionTags));
    }

    /**
     * Update an existing session with new data.
     *
     * @throws RuntimeException
     */
    public function update(SessionData $sessionData): void
    {
        if (! $this->exists($sessionData->metadata->name)) {
            throw new RuntimeException(
                "Session '{$sessionData->metadata->name}' does not exist."
            );
        }

        $sessionPath = $this->getSessionPath($sessionData->metadata->name);
        $json = json_encode($sessionData->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Failed to encode session data: ' . json_last_error_msg());
        }

        if (File::put($sessionPath, $json) === false) {
            throw new RuntimeException("Failed to update session: {$sessionPath}");
        }
    }

    /**
     * Delete a session.
     *
     * @throws RuntimeException
     */
    public function delete(SessionName|string $name): bool
    {
        $sessionName = $name instanceof SessionName ? $name : SessionName::from($name);

        if (! $this->exists($sessionName)) {
            throw new RuntimeException("Session '{$sessionName}' does not exist.");
        }

        return File::delete($this->getSessionPath($sessionName));
    }

    /**
     * Get the full path to a session file.
     */
    protected function getSessionPath(SessionName $name): string
    {
        $safeName = basename($name->toString());

        if (! str_ends_with($safeName, '.json')) {
            $safeName .= '.json';
        }

        return $this->storagePath . '/' . $safeName;
    }

    /**
     * Ensure the storage directory exists.
     */
    protected function ensureStorageDirectoryExists(): void
    {
        if (! File::isDirectory($this->storagePath)) {
            File::makeDirectory($this->storagePath, 0755, true);
        }
    }
}
