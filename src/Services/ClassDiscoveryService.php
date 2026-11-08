<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

use Symfony\Component\Finder\Finder;

readonly class ClassDiscoveryService
{
    /**
     * @param array<string>|null $scannableNamespaces
     */
    public function __construct(
        private ?array $scannableNamespaces = null
    ) {}

    /**
     * Discover all classes in the application's App namespace.
     *
     * @return array<string, string> Map of fully qualified class name => file path
     */
    public function discoverClasses(): array
    {
        $classes = [];

        foreach ($this->getScannableDirectories() as [$namespace, $directory]) {
            $classes = array_merge($classes, $this->scanDirectory($namespace, $directory));
        }

        return $classes;
    }

    /**
     * Hash of the scanned files and their modification times, without reading them.
     */
    public function fingerprint(): string
    {
        $autoloadFile = $this->getComposerAutoloadPath();
        $parts = [$autoloadFile . ':' . (file_exists($autoloadFile) ? filemtime($autoloadFile) : 0)];

        foreach ($this->getScannableDirectories() as [$namespace, $directory]) {
            $parts[] = $namespace . '=' . $directory;

            try {
                foreach ($this->createFinder($directory) as $file) {
                    $parts[] = $file->getPathname() . ':' . $file->getMTime();
                }
            } catch (\Exception) {
                continue;
            }
        }

        sort($parts);

        return md5(implode("\n", $parts));
    }

    /**
     * @return array<int, array{0: string, 1: string}> Pairs of namespace and directory
     */
    private function getScannableDirectories(): array
    {
        $directories = [];

        foreach ($this->getComposerPsr4Mappings() as $namespace => $paths) {
            if (! $this->shouldScanNamespace($namespace)) {
                continue;
            }

            foreach ((array) $paths as $directory) {
                if (is_dir($directory)) {
                    $directories[] = [$namespace, $directory];
                }
            }
        }

        return $directories;
    }

    private function createFinder(string $directory): Finder
    {
        return (new Finder())
            ->files()
            ->in($directory)
            ->name('*.php')
            ->notPath('database/migrations')
            ->notPath('database/seeders')
            ->notPath('tests')
            ->ignoreDotFiles(true);
    }

    /**
     * Get Composer PSR-4 autoloader mappings.
     *
     * @return array<string, string|array<string>>
     */
    private function getComposerPsr4Mappings(): array
    {
        $autoloadFile = $this->getComposerAutoloadPath();

        if (! file_exists($autoloadFile)) {
            return [];
        }

        return require $autoloadFile;
    }

    /**
     * Get the path to Composer's PSR-4 autoload file.
     */
    private function getComposerAutoloadPath(): string
    {
        return base_path('vendor/composer/autoload_psr4.php');
    }

    /**
     * Check if namespace should be scanned.
     */
    private function shouldScanNamespace(string $namespace): bool
    {
        $namespaces = $this->scannableNamespaces
            ?? config('tailor.discovery.scannable_namespaces', ['App\\']);

        foreach ($namespaces as $scannableNamespace) {
            if (str_starts_with($namespace, $scannableNamespace)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scan a directory for PHP class files.
     *
     * @return array<string, string>
     */
    private function scanDirectory(string $baseNamespace, string $directory): array
    {
        $classes = [];

        try {
            foreach ($this->createFinder($directory) as $file) {
                $classNames = $this->extractClassNames($file->getContents());

                foreach ($classNames as $className) {
                    $relativePath = str_replace($directory, '', $file->getRealPath());
                    $relativePath = str_replace(['/', '.php'], ['\\', ''], ltrim($relativePath, '/\\'));

                    $fqn = rtrim($baseNamespace, '\\') . '\\' . $relativePath;

                    if (! str_ends_with($fqn, '\\' . $className)) {
                        $pathParts = explode('\\', $relativePath);
                        array_pop($pathParts);
                        $fqn = rtrim($baseNamespace, '\\') . '\\' . implode('\\', $pathParts) . '\\' . $className;
                    }

                    $classes[$fqn] = $file->getRealPath();
                }
            }
        } catch (\Exception $e) {
            return [];
        }

        return $classes;
    }

    /**
     * Extract class, interface, and trait names from PHP file contents.
     *
     * @return array<string>
     */
    private function extractClassNames(string $contents): array
    {
        $classes = [];
        $tokens = @token_get_all($contents);

        if (empty($tokens)) {
            return [];
        }

        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (! is_array($tokens[$i])) {
                continue;
            }

            if (in_array($tokens[$i][0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                if ($this->previousSignificantToken($tokens, $i) === T_DOUBLE_COLON) {
                    continue;
                }

                for ($j = $i + 1; $j < $count; $j++) {
                    if (! is_array($tokens[$j])) {
                        break;
                    }

                    if ($tokens[$j][0] === T_WHITESPACE) {
                        continue;
                    }

                    if ($tokens[$j][0] === T_STRING) {
                        $classes[] = $tokens[$j][1];
                        break;
                    }

                    break;
                }
            }
        }

        return $classes;
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function previousSignificantToken(array $tokens, int $index): int|string|null
    {
        for ($k = $index - 1; $k >= 0; $k--) {
            $token = $tokens[$k];

            if (! is_array($token)) {
                return $token;
            }

            if (! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $token[0];
            }
        }

        return null;
    }
}
