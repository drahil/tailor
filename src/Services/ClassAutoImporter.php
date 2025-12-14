<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

use drahil\Tailor\Support\DTOs\ImportResult;
use Exception;
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

            $useStatements = $this->importManager->generateUseStatements($classes);
            $includeFile = $this->fileManager->createIncludeFile($useStatements);

            return ImportResult::success($includeFile);
        } catch (Exception $e) {
            $output->writeln('<error>Auto-import failed: ' . $e->getMessage() . '</error>');
            $output->writeln('<error>at ' . $e->getFile() . ':' . $e->getLine() . '</error>');
            return ImportResult::failed();
        }
    }
}
