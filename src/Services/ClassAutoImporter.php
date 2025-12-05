<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

use drahil\Tailor\ValueObjects\ImportResult;
use Exception;
use Symfony\Component\Console\Output\OutputInterface;

class ClassAutoImporter
{
    public function __construct(
        private readonly ClassDiscoveryCache $cache,
        private readonly ClassDiscoveryService $discoveryService,
        private readonly ClassImportManager $importManager,
        private readonly IncludeFileManager $fileManager,
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

            $output->writeln('<info>Auto-importing ' . count($classes) . ' classes...</info>');

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
