<?php

declare(strict_types=1);

namespace drahil\Tailor\Console\Commands;

use drahil\Tailor\Services\ClassAutoImporter;
use drahil\Tailor\Services\IncludeFileManager;
use drahil\Tailor\Services\TailorShellBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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
        $this
            ->setDescription('A Laravel REPL with saved sessions, auto-imported classes and Eloquent-aware autocomplete')
            ->addArgument('include', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'PHP files to run before the shell opens');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $includes = $input->getArgument('include');

        foreach ($includes as $file) {
            if (! is_file($file)) {
                $output->writeln("<error>File not found: {$file}</error>");
                return Command::FAILURE;
            }
        }

        $importResult = $this->autoImporter->prepare($output);

        try {
            $this->shellBuilder->build($importResult, $includes)->run();
        } finally {
            if ($importResult->hasIncludeFile()) {
                $this->fileManager->cleanup($importResult->getIncludeFile());
            }
        }

        return Command::SUCCESS;
    }
}
