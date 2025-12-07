<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH\SessionCommands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SessionListCommand extends SessionCommand
{
    protected function configure(): void
    {
        $this
            ->setName('session:list')
            ->setAliases(['sessions'])
            ->setDescription('List all saved sessions')
            ->setHelp('Display a list of all saved Tailor sessions');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sessionManager = $this->getSessionManager();

        $sessions = $sessionManager->list();

        if (empty($sessions)) {
            $output->writeln('<comment>No saved sessions found.</comment>');
            return 0;
        }

        $output->writeln('<info>Saved Sessions:</info>');
        $output->writeln('');

        foreach ($sessions as $session) {
            $output->writeln(sprintf(
                '  <fg=cyan>%s</> - %s (%d commands)',
                $session['name'],
                $session['created_at'],
                $session['command_count']
            ));
        }

        return 0;
    }
}
