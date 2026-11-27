<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

class ClassDiscoveryCache
{
    private string $cacheDir;

    public function __construct()
    {
        $this->cacheDir = storage_path('tailor');
    }

    /**
     * Get the cached class map while the scanned files are unchanged, otherwise discover and cache.
     *
     * @return array<string, string>
     */
    public function getOrDiscover(ClassDiscoveryService $discoveryService): array
    {
        $fingerprint = $discoveryService->fingerprint();
        $cached = $this->readCache();

        if ($cached !== null && $cached['fingerprint'] === $fingerprint) {
            return $cached['classes'];
        }

        $classes = $discoveryService->discoverClasses();
        $this->writeCache($fingerprint, $classes);

        return $classes;
    }

    /**
     * @return array{fingerprint: string, classes: array<string, string>}|null
     */
    private function readCache(): ?array
    {
        $cacheFile = $this->getCacheFilePath();
        $content = file_exists($cacheFile) ? @file_get_contents($cacheFile) : false;

        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);

        if (! is_array($data) || ! is_string($data['fingerprint'] ?? null) || ! is_array($data['classes'] ?? null)) {
            return null;
        }

        return ['fingerprint' => $data['fingerprint'], 'classes' => $data['classes']];
    }

    /**
     * @param array<string, string> $classes
     */
    private function writeCache(string $fingerprint, array $classes): void
    {
        if (! is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }

        @file_put_contents(
            $this->getCacheFilePath(),
            json_encode(['fingerprint' => $fingerprint, 'classes' => $classes], JSON_UNESCAPED_SLASHES)
        );
    }

    private function getCacheFilePath(): string
    {
        return $this->cacheDir . '/class_cache.json';
    }

    /**
     * Clear the cache.
     */
    public function clear(): void
    {
        $cacheFile = $this->getCacheFilePath();

        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
    }
}
