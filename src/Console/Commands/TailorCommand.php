<?php

declare(strict_types=1);

namespace drahil\Tailor\Console\Commands;

use drahil\Tailor\Services\ClassAutoImporter;
use drahil\Tailor\Services\IncludeFileManager;
use drahil\Tailor\Services\ShellFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TailorCommand extends Command
{
    public function __construct(
        private readonly ClassAutoImporter $autoImporter,
        private readonly ShellFactory $shellFactory,
        private readonly IncludeFileManager $fileManager,
    ) {
        parent::__construct('tailor');
    }

    public function configure(): void
    {
        $this->setDescription('Interactive REPL with auto-imported classes');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $importResult = $this->autoImporter->prepare($output);

        $config = $this->shellFactory->createConfiguration();
        $shell = $this->shellFactory->create($config);

        if ($importResult->hasIncludeFile()) {
            $shell->setIncludes([$importResult->getIncludeFile()]);
        }

        $shell->run();

        if ($importResult->hasIncludeFile()) {
            $this->fileManager->cleanup($importResult->getIncludeFile());
        }

        return Command::SUCCESS;
    }
}
