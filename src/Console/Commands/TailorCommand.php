<?php

declare(strict_types=1);

namespace drahil\Tailor\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psy\Shell;
use Psy\Configuration;

class TailorCommand extends Command
{
    public function __construct()
    {
        parent::__construct('tailor');
    }

    public function configure(): void
    {
        $this->setDescription('Tailor command description');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = new Configuration([
            'startupMessage' => 'Welcome to Tailor!',
            'historyFile' => getcwd() . '/.tailor_history',
        ]);

        $shell = new Shell($config);
        $shell->run();

        return Command::SUCCESS;
    }
}