<?php

declare(strict_types=1);

namespace drahil\Tailor\Support;

use DateTime;
use Illuminate\Support\Facades\File;
use RuntimeException;

class SessionManager
{
    /**
     * Path to the sessions storage directory.
     *
     * @var string
     */
    protected string $storagePath;

    /**
     * Create a new SessionManager instance.
     *
     * @param string|null $storagePath
     */
    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? config('tailor.storage.sessions', storage_path('tailor/sessions'));
        $this->ensureStorageDirectoryExists();
    }

    /**
     * Check if a session exists.
     *
     * @param string $name
     * @return bool
     */
    public function exists(string $name): bool
    {
        return File::exists($this->getSessionPath($name));
    }

    /**
     * Save a session.
     *
     * @param string $name
     * @param SessionTracker $tracker
     * @param array $metadata
     * @return void
     * @throws RuntimeException
     */
    public function save(string $name, SessionTracker $tracker, array $metadata = []): void
    {
        $sessionPath = $this->getSessionPath($name);

        $existingSession = $this->exists($name) ? $this->load($name) : null;

        $sessionData = [
            'name' => $name,
            'description' => $metadata['description'] ?? null,
            'tags' => $metadata['tags'] ?? [],
            'created_at' => $existingSession['created_at'] ?? (new DateTime())->format('c'),
            'updated_at' => (new DateTime())->format('c'),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'commands' => $tracker->getCommands(),
            'variables' => $tracker->getVariables(),
            'metadata' => [
                'total_commands' => $tracker->getCommandCount(),
                'duration_seconds' => $tracker->getDuration(),
                'project_path' => base_path(),
                'started_at' => $tracker->getStartedAt()?->format('c'),
            ],
        ];

        $json = json_encode($sessionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Failed to encode session data to JSON: ' . json_last_error_msg());
        }

        if (File::put($sessionPath, $json) === false) {
            throw new RuntimeException("Failed to write session to file: {$sessionPath}");
        }
    }

    /**
     * Load a session.
     *
     * @param string $name
     * @return array
     * @throws RuntimeException
     */
    public function load(string $name): array
    {
        if (! $this->exists($name)) {
            throw new RuntimeException("Session '{$name}' does not exist.");
        }

        $sessionPath = $this->getSessionPath($name);
        $json = File::get($sessionPath);

        $sessionData = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                "Failed to decode session '{$name}': " . json_last_error_msg()
            );
        }

        return $sessionData;
    }

    /**
     * List all available sessions.
     *
     * @return array
     */
    public function list(): array
    {
        $this->ensureStorageDirectoryExists();

        $files = File::glob($this->storagePath . '/*.json');
        $sessions = [];

        foreach ($files as $file) {
            try {
                $sessionData = json_decode(File::get($file), true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $sessions[] = [
                        'name' => $sessionData['name'] ?? basename($file, '.json'),
                        'description' => $sessionData['description'] ?? null,
                        'tags' => $sessionData['tags'] ?? [],
                        'created_at' => $sessionData['created_at'] ?? null,
                        'updated_at' => $sessionData['updated_at'] ?? null,
                        'command_count' => $sessionData['metadata']['total_commands'] ?? count($sessionData['commands'] ?? []),
                        'laravel_version' => $sessionData['laravel_version'] ?? null,
                    ];
                }
            } catch (\Throwable $e) {
                // Skip corrupted session files
                continue;
            }
        }

        usort($sessions, function ($a, $b) {
            return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
        });

        return $sessions;
    }

    /**
     * Delete a session.
     *
     * @param string $name
     * @return bool
     * @throws RuntimeException
     */
    public function delete(string $name): bool
    {
        if (! $this->exists($name)) {
            throw new RuntimeException("Session '{$name}' does not exist.");
        }

        return File::delete($this->getSessionPath($name));
    }

    /**
     * Get detailed information about a session.
     *
     * @param string $name
     * @return array
     * @throws RuntimeException
     */
    public function getInfo(string $name): array
    {
        return $this->load($name);
    }

    /**
     * Get the full path to a session file.
     *
     * @param string $name
     * @return string
     */
    protected function getSessionPath(string $name): string
    {
        $safeName = basename($name);

        if (! str_ends_with($safeName, '.json')) {
            $safeName .= '.json';
        }

        return $this->storagePath . '/' . $safeName;
    }

    /**
     * Ensure the storage directory exists.
     *
     * @return void
     */
    protected function ensureStorageDirectoryExists(): void
    {
        if (! File::isDirectory($this->storagePath)) {
            File::makeDirectory($this->storagePath, 0755, true);
        }
    }

    /**
     * Get the storage path.
     *
     * @return string
     */
    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    /**
     * Get all session names.
     *
     * @return array
     */
    public function getSessionNames(): array
    {
        return array_column($this->list(), 'name');
    }

    /**
     * Check if any sessions exist.
     *
     * @return bool
     */
    public function hasSessions(): bool
    {
        return count($this->getSessionNames()) > 0;
    }
}
