<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

use drahil\Tailor\Support\DTOs\SessionData;
use drahil\Tailor\Support\DTOs\SessionMetadata;
use drahil\Tailor\Support\Storage\SessionFile;
use drahil\Tailor\Support\ValueObjects\SessionName;
use Illuminate\Support\Facades\File;
use Psy\Util\Str;
use RuntimeException;
use Throwable;

/**
 * Stores sessions as editable PHP files.
 */
class SessionManager
{
    protected string $storagePath;

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? config('tailor.storage.sessions');
        $this->ensureStorageDirectoryExists();
        $this->convertLegacySessions();
    }

    public function exists(SessionName|string $name): bool
    {
        return File::exists($this->getSessionPath($this->toSessionName($name)));
    }

    /**
     * Save a session with metadata and tracker data, keeping the creation date of an existing session.
     *
     * @throws RuntimeException
     */
    public function save(SessionMetadata $metadata, SessionTracker $tracker): void
    {
        $existingCreatedAt = null;
        if ($this->exists($metadata->name)) {
            try {
                $existingCreatedAt = $this->load($metadata->name)->metadata->createdAt;
            } catch (RuntimeException) {
            }
        }

        $sessionData = SessionData::fromTracker(
            new SessionMetadata(
                name: $metadata->name,
                description: $metadata->description,
                tags: $metadata->tags,
                createdAt: $existingCreatedAt ?? $metadata->createdAt,
                updatedAt: $metadata->updatedAt,
            ),
            $tracker
        );

        $this->write($this->getSessionPath($metadata->name), $sessionData);
    }

    /**
     * Append commands to the end of an existing session.
     *
     * @param array<int, string> $commands
     * @throws RuntimeException
     */
    public function append(SessionName $name, array $commands): SessionData
    {
        $existing = $this->load($name);

        $sessionData = new SessionData(
            $existing->metadata->withUpdatedTimestamp(),
            [...$existing->commands, ...$commands],
        );

        $this->write($this->getSessionPath($name), $sessionData);

        return $sessionData;
    }

    /**
     * @throws RuntimeException
     */
    public function load(SessionName|string $name): SessionData
    {
        $sessionName = $this->toSessionName($name);

        if (! $this->exists($sessionName)) {
            throw new RuntimeException("Session '{$sessionName}' does not exist.");
        }

        $sessionPath = $this->getSessionPath($sessionName);

        try {
            return SessionFile::parse(File::get($sessionPath), $sessionName);
        } catch (Throwable $e) {
            throw new RuntimeException(
                "Session '{$sessionName}' is damaged ({$e->getMessage()}). Fix or delete {$sessionPath}, or replace it with 'session:save {$sessionName} --force'."
            );
        }
    }

    /**
     * List all sessions, newest first, optionally only those that have all of the given tags.
     *
     * @param array<string> $filterTags
     * @return array<int, array{
     *     name: string,
     *     description: string|null,
     *     tags: array<int, string>,
     *     created_at: string|null,
     *     updated_at: string|null,
     *     command_count: int}>
     */
    public function list(array $filterTags = []): array
    {
        $this->ensureStorageDirectoryExists();

        $sessions = [];

        foreach (File::glob($this->storagePath . '/*.php') as $file) {
            try {
                $sessionData = SessionFile::parse(File::get($file), SessionName::from(basename($file, '.php')));
            } catch (Throwable) {
                continue;
            }

            $metadata = $sessionData->metadata;

            if ($filterTags !== [] && ! $this->hasAllTags($metadata->tags, $filterTags)) {
                continue;
            }

            $sessions[] = [
                'name' => $metadata->name->toString(),
                'description' => $metadata->description?->toString(),
                'tags' => $metadata->tags,
                'created_at' => $metadata->createdAt?->format('c'),
                'updated_at' => $metadata->updatedAt?->format('c'),
                'command_count' => $sessionData->getCommandCount(),
            ];
        }

        usort($sessions, fn ($a, $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));

        return $sessions;
    }

    /**
     * @throws RuntimeException
     */
    public function update(SessionData $sessionData): void
    {
        if (! $this->exists($sessionData->metadata->name)) {
            throw new RuntimeException("Session '{$sessionData->metadata->name}' does not exist.");
        }

        $this->write($this->getSessionPath($sessionData->metadata->name), $sessionData);
    }

    /**
     * @throws RuntimeException
     */
    public function delete(SessionName|string $name): bool
    {
        $sessionName = $this->toSessionName($name);

        if (! $this->exists($sessionName)) {
            throw new RuntimeException("Session '{$sessionName}' does not exist.");
        }

        return File::delete($this->getSessionPath($sessionName));
    }

    /**
     * Get the session file path, also matching files saved under a different letter case.
     */
    protected function getSessionPath(SessionName $name): string
    {
        $path = $this->storagePath . '/' . $name->toString() . '.php';

        if (file_exists($path)) {
            return $path;
        }

        foreach (File::glob($this->storagePath . '/*.php') as $file) {
            if (strtolower(basename($file)) === basename($path)) {
                return $file;
            }
        }

        return $path;
    }

    protected function ensureStorageDirectoryExists(): void
    {
        if (! File::isDirectory($this->storagePath)) {
            File::makeDirectory($this->storagePath, 0755, true);
        }
    }

    /**
     * Convert sessions saved by earlier versions as JSON into PHP files.
     * Files that cannot be converted are left untouched.
     */
    private function convertLegacySessions(): void
    {
        foreach (File::glob($this->storagePath . '/*.json') as $jsonFile) {
            try {
                $name = SessionName::from(basename($jsonFile, '.json'));

                if ($this->exists($name)) {
                    continue;
                }

                $data = json_decode(File::get($jsonFile), true, flags: JSON_THROW_ON_ERROR);
                $commands = [];

                foreach ($data['commands'] ?? [] as $command) {
                    $code = $command['code'];

                    if ($code !== '_HiStOrY_V2_') {
                        $commands[] = str_contains($code, '\\040') ? Str::unvis($code) : $code;
                    }
                }

                $metadata = SessionMetadata::fromArray(['name' => $name->toString()] + $data);
                $this->write($this->getSessionPath($name), new SessionData($metadata, $commands));
                File::delete($jsonFile);
            } catch (Throwable) {
                continue;
            }
        }
    }

    /**
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
     * Write session data atomically through a temporary file and rename.
     *
     * @throws RuntimeException
     */
    private function write(string $sessionPath, SessionData $sessionData): void
    {
        $tempPath = @tempnam(dirname($sessionPath), 'tailor-session-');

        if ($tempPath === false || file_put_contents($tempPath, SessionFile::render($sessionData)) === false) {
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

    private function toSessionName(SessionName|string $name): SessionName
    {
        return $name instanceof SessionName ? $name : SessionName::from($name);
    }
}
