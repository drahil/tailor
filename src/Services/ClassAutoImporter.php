<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

use drahil\Tailor\Support\DTOs\ImportResult;
use Exception;
use Illuminate\Foundation\AliasLoader;
use Symfony\Component\Console\Output\OutputInterface;

readonly class ClassAutoImporter
{
    public function __construct(
        private ClassDiscoveryCache $cache,
        private ClassDiscoveryService $discoveryService,
        private ClassImportManager $importManager,
        private IncludeFileManager $fileManager,
    ) {}

    /**
     * Prepare auto-imports by discovering classes and generating an include file.
     */
    public function prepare(OutputInterface $output): ImportResult
    {
        try {
            $classes = $this->cache->getOrDiscover($this->discoveryService);

            if (empty($classes)) {
                $output->writeln('<comment>No App classes found to auto-import.</comment>');
                return ImportResult::empty();
            }

            $aliases = $this->importManager->generateAliases($classes, $this->reservedNames());
            $this->reportSkipped($output, $aliases['skipped']);

            $includeFile = $this->fileManager->createIncludeFile($aliases['statements']);

            return ImportResult::success($includeFile);
        } catch (Exception $e) {
            $output->writeln('<error>Auto-import failed: ' . $e->getMessage() . '</error>');
            $output->writeln('<error>at ' . $e->getFile() . ':' . $e->getLine() . '</error>');
            return ImportResult::failed();
        }
    }

    /**
     * Facade aliases and global classes, which App classes must not shadow.
     *
     * @return array<int, string>
     */
    private function reservedNames(): array
    {
        $facadeAliases = class_exists(AliasLoader::class)
            ? array_keys(AliasLoader::getInstance()->getAliases())
            : array_keys(config('app.aliases', []));

        $globalClasses = array_filter(
            [...get_declared_classes(), ...get_declared_interfaces(), ...get_declared_traits()],
            fn (string $name) => ! str_contains($name, '\\')
        );

        return [...$facadeAliases, ...$globalClasses];
    }

    /**
     * @param array<string, string> $skipped
     */
    private function reportSkipped(OutputInterface $output, array $skipped): void
    {
        if ($skipped === []) {
            return;
        }

        $names = array_map(
            fn (string $fqn, string $alias) => "{$alias} ({$fqn})",
            array_keys($skipped),
            $skipped
        );

        $output->writeln('<comment>Not auto-imported, name already in use: ' . implode(', ', $names) . '. Use the full class name.</comment>');
    }
}
