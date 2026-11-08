<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

use DateTime;
use drahil\Tailor\Support\DTOs\SessionData;
use drahil\Tailor\Support\DTOs\SessionMetadata;
use drahil\Tailor\Support\ValueObjects\SessionName;
use Exception;
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
            try {
                $existingCreatedAt = $this->load($metadata->name)->metadata->createdAt;
            } catch (RuntimeException) {
            }
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

        $this->write($sessionPath, $sessionData);
    }

    /**
     * Append commands to the end of an existing session.
     *
     * @param array<int, string> $commands
     * @throws RuntimeException|FileNotFoundException|Exception
     */
    public function append(SessionName $name, array $commands): SessionData
    {
        $existing = $this->load($name);
        $allCommands = $existing->commands;
        $timestamp = (new DateTime())->format('Y-m-d H:i:s');

        foreach ($commands as $code) {
            $allCommands[] = [
                'code' => $code,
                'output' => null,
                'timestamp' => $timestamp,
                'order' => count($allCommands) + 1,
            ];
        }

        $sessionData = new SessionData(
            metadata: $existing->metadata->withUpdatedTimestamp(),
            commands: $allCommands,
            variables: $existing->variables,
            sessionMetadata: [...$existing->sessionMetadata, 'total_commands' => count($allCommands)],
        );

        $this->write($this->getSessionPath($name), $sessionData);

        return $sessionData;
    }

    /**
     * Write session data atomically through a temporary file and rename.
     *
     * @throws RuntimeException
     */
    private function write(string $sessionPath, SessionData $sessionData): void
    {
        $json = json_encode($sessionData->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Failed to encode session data to JSON: ' . json_last_error_msg());
        }

        $tempPath = @tempnam(dirname($sessionPath), 'tailor-session-');

        if ($tempPath === false || file_put_contents($tempPath, $json) === false) {
            if ($tempPath !== false) {
                @unlink($tempPath);
            }

            throw new RuntimeException("Failed to write session to file: {$sessionPath}");
        }

        @chmod($tempPath, 0666 & ~umask());

        if (! rename($tempPath, $sessionPath)) {
            @unlink($tempPath);

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
        $data = json_decode(File::get($sessionPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw $this->damagedSession($sessionName, $sessionPath, json_last_error_msg());
        }

        if (! is_array($data)) {
            throw $this->damagedSession($sessionName, $sessionPath, 'not a JSON object');
        }

        if (! $this->hasValidCommands($data['commands'] ?? [])) {
            throw $this->damagedSession($sessionName, $sessionPath, 'invalid commands');
        }

        $data['name'] = $sessionName->toString();

        try {
            return SessionData::fromArray($data);
        } catch (Throwable $e) {
            throw $this->damagedSession($sessionName, $sessionPath, $e->getMessage());
        }
    }

    private function hasValidCommands(mixed $commands): bool
    {
        if (! is_array($commands)) {
            return false;
        }

        foreach ($commands as $command) {
            if (! is_array($command) || ! is_string($command['code'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function damagedSession(SessionName $name, string $path, string $reason): RuntimeException
    {
        return new RuntimeException(
            "Session '{$name}' is damaged ({$reason}). Fix or delete {$path}, or replace it with 'session:save {$name} --force'."
        );
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
        return empty(array_diff(
            array_map('strtolower', $filterTags),
            array_map('strtolower', $sessionTags)
        ));
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

        $this->write($this->getSessionPath($sessionData->metadata->name), $sessionData);
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
        $path = $this->storagePath . '/' . $name->toString() . '.json';

        if (file_exists($path)) {
            return $path;
        }

        foreach (File::glob($this->storagePath . '/*.json') as $file) {
            if (strtolower(basename($file)) === basename($path)) {
                return $file;
            }
        }

        return $path;
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
