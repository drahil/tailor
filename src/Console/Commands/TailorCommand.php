<?php

declare(strict_types=1);

namespace drahil\Tailor\Console\Commands;

use drahil\Tailor\Services\ClassAutoImporter;
use drahil\Tailor\Services\IncludeFileManager;
use drahil\Tailor\Services\TailorShellBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TailorCommand extends Command
{
    public function __construct(
        private readonly ClassAutoImporter $autoImporter,
        private readonly IncludeFileManager $fileManager,
        private readonly TailorShellBuilder $shellBuilder,
    ) {
        parent::__construct('tailor');
    }

    public function configure(): void
    {
        $this->setDescription('Enhanced Laravel Tinker with session management and auto-imported classes');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $importResult = $this->autoImporter->prepare($output);

        if (! $importResult->isSuccessful()) {
            return Command::FAILURE;
        }

        $shell = $this->shellBuilder->build($importResult);

        $shell->run();

        if ($importResult->hasIncludeFile()) {
            $this->fileManager->cleanup($importResult->getIncludeFile());
        }

        return Command::SUCCESS;
    }
}
