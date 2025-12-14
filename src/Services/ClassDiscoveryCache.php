<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

class ClassDiscoveryCache
{
    private string $cacheDir;

    public function __construct()
    {
        $this->cacheDir = $this->determineCacheDirectory();
    }

    /**
     * Get cached class map if valid, otherwise discover and cache.
     *
     * @return array<string, string>
     */
    public function getOrDiscover(ClassDiscoveryService $discoveryService): array
    {
        if ($this->isCacheValid()) {
            $cached = $this->readCache();

            if ($cached !== null) {
                return $cached;
            }
        }

        $classes = $discoveryService->discoverClasses();
        $this->writeCache($classes);

        return $classes;
    }

    /**
     * Check if cache is valid based on composer.lock modification time.
     */
    private function isCacheValid(): bool
    {
        $cacheFile = $this->getCacheFilePath();

        if (! file_exists($cacheFile)) {
            return false;
        }

        $composerLockPath = $this->getComposerLockPath();

        if (! file_exists($composerLockPath)) {
            return true;
        }

        $cacheTime = filemtime($cacheFile);
        $composerTime = filemtime($composerLockPath);

        return $cacheTime !== false && $composerTime !== false && $cacheTime >= $composerTime;
    }

    /**
     * Read cached class map.
     *
     * @return array<string, string>|null
     */
    private function readCache(): ?array
    {
        $cacheFile = $this->getCacheFilePath();

        if (! file_exists($cacheFile)) {
            return null;
        }

        $content = @file_get_contents($cacheFile);

        if ($content === false) {
            return null;
        }

        $data = @unserialize($content);

        return is_array($data) ? $data : null;
    }

    /**
     * Write class map to cache.
     *
     * @param array<string, string> $classes
     */
    private function writeCache(array $classes): void
    {
        $cacheFile = $this->getCacheFilePath();
        $cacheDir = dirname($cacheFile);

        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        @file_put_contents($cacheFile, serialize($classes));
    }

    /**
     * Get cache file path.
     */
    private function getCacheFilePath(): string
    {
        return $this->cacheDir . '/tailor_class_cache.php';
    }

    /**
     * Get composer.lock file path.
     */
    private function getComposerLockPath(): string
    {
        $vendorDir = getcwd() . '/vendor';

        for ($i = 0; $i < 5; $i++) {
            $lockPath = dirname($vendorDir) . '/composer.lock';

            if (file_exists($lockPath)) {
                return $lockPath;
            }

            $vendorDir = dirname($vendorDir, 2) . '/vendor';
        }

        return '';
    }

    /**
     * Determine cache directory.
     */
    private function determineCacheDirectory(): string
    {
        $vendorDir = getcwd() . '/vendor';

        for ($i = 0; $i < 5; $i++) {
            $storageDir = dirname($vendorDir) . '/storage/tailor';

            if (is_dir(dirname($storageDir))) {
                return $storageDir;
            }

            $vendorDir = dirname($vendorDir, 2) . '/vendor';
        }

        return sys_get_temp_dir() . '/tailor';
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
