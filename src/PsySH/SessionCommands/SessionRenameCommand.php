<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH\SessionCommands;

use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SessionRenameCommand extends SessionCommand
{
    protected function configure(): void
    {
        $this
            ->setName('session:rename')
            ->setDescription('Rename a saved session')
            ->setHelp(<<<HELP
  Rename a saved session. If a session with the new name already exists,
  you will be asked before it is replaced, unless --force is used.

  Usage:
    session:rename old-name new-name            Rename a session
    session:rename old-name new-name --force    Replace an existing session without asking

  Examples:
    >>> session:rename session-2026-11-25-101500 blog-stats
  HELP
            )
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Current name of the session'
            )
            ->addArgument(
                'new-name',
                InputArgument::REQUIRED,
                'New name for the session'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Replace an existing session with the new name without asking'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $from = $this->validateSessionName($input->getArgument('name'), $output);
        if (! $from) {
            return 1;
        }

        $to = $this->validateSessionName($input->getArgument('new-name'), $output);
        if (! $to) {
            return 1;
        }

        if (! $this->sessionExists($from->toString())) {
            return $this->sessionNotFoundError($output, (string) $from);
        }

        if ($from->toString() === $to->toString()) {
            $output->writeln("<comment>Session '{$from}' already has that name.</comment>");
            return 0;
        }

        if ($this->sessionExists($to->toString()) && ! $input->getOption('force')) {
            $output->writeln("<comment>Session '{$to}' already exists.</comment>");

            if (! $this->confirm($input, $output, "Replace '{$to}' with '{$from}'?")) {
                $output->writeln('<info>Rename cancelled.</info>');
                return 0;
            }
        }

        try {
            $this->getSessionManager()->rename($from, $to);
        } catch (Exception $e) {
            return $this->operationFailedError($output, 'rename', $e->getMessage());
        }

        $sessionTracker = $this->getApplication()->getScopeVariable('__sessionTracker');
        if ($sessionTracker->getActiveSession() === $from->toString()) {
            $sessionTracker->setActiveSession($to->toString());
        }

        $output->writeln("<info>✓ Renamed '{$from}' to '{$to}'</info>");

        return 0;
    }
}
