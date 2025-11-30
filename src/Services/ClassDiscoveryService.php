<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

use Symfony\Component\Finder\Finder;

class ClassDiscoveryService
{
    /**
     * Discover all classes in the application's App namespace.
     *
     * @return array<string, string> Map of fully qualified class name => file path
     */
    public function discoverClasses(): array
    {
        $psr4Mappings = $this->getComposerPsr4Mappings();
        $classes = [];

        foreach ($psr4Mappings as $namespace => $directories) {
            if (! $this->shouldScanNamespace($namespace)) {
                continue;
            }

            foreach ((array) $directories as $directory) {
                if (! is_dir($directory)) {
                    continue;
                }

                $discovered = $this->scanDirectory($namespace, $directory);
                $classes = array_merge($classes, $discovered);
            }
        }

        return $classes;
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
        $vendorDir = getcwd() . '/vendor';

        for ($i = 0; $i < 5; $i++) {
            $autoloadPath = $vendorDir . '/composer/autoload_psr4.php';

            if (file_exists($autoloadPath)) {
                return $autoloadPath;
            }

            $vendorDir = dirname($vendorDir, 2) . '/vendor';
        }

        return '';
    }

    /**
     * Check if namespace should be scanned.
     */
    private function shouldScanNamespace(string $namespace): bool
    {
        return str_starts_with($namespace, 'App\\');
    }

    /**
     * Scan a directory for PHP class files.
     *
     * @return array<string, string>
     */
    private function scanDirectory(string $baseNamespace, string $directory): array
    {
        $classes = [];
        $finder = new Finder();

        try {
            $finder->files()
                ->in($directory)
                ->name('*.php')
                ->notPath('database/migrations')
                ->notPath('database/seeders')
                ->notPath('tests')
                ->ignoreDotFiles(true);

            foreach ($finder as $file) {
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

        if ($tokens === false) {
            return [];
        }

        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (! is_array($tokens[$i])) {
                continue;
            }

            if (in_array($tokens[$i][0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if (! is_array($tokens[$j])) {
                        continue;
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
}
