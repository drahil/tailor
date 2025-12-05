<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH;

use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SessionDeleteCommand extends SessionCommand
{
    protected function configure(): void
    {
        $this
            ->setName('session:delete')
            ->setAliases(['delete'])
            ->setDescription('Delete a saved Tailor session')
            ->setHelp(
                <<<HELP
  Delete a previously saved Tailor session by name.
  You will be prompted for confirmation unless the --force flag is used.
  You can list available sessions with the 'session:list' command.

  Usage:
    session:delete my-session                   Delete session with confirmation
    session:delete my-session --force           Delete without confirmation

  Examples:
    >>> session:delete testing
    >>> session:delete old-work -f
  HELP
            )
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Name of the session to delete'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Delete without confirmation'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sessionManager = $this->getSessionManager();
        $nameInput = $input->getArgument('name');

        $sessionName = $this->validateSessionName($nameInput, $output);
        if (! $sessionName) {
            return 1;
        }

        if (! $this->sessionExists($sessionName->toString())) {
            return $this->sessionNotFoundError($output, (string) $sessionName);
        }

        $force = $input->getOption('force');
        if (! $force) {
            if (! $this->confirm($output, "Are you sure you want to delete session '{$sessionName}'?")) {
                $output->writeln('<info>Delete cancelled.</info>');
                return 0;
            }
        }

        try {
            $sessionManager->delete($sessionName);

            $output->writeln('');
            $output->writeln("<info>✓ Session '{$sessionName}' deleted successfully!</info>");
            $output->writeln('');

            return 0;

        } catch (Exception $e) {
            return $this->operationFailedError($output, 'delete', $e->getMessage());
        }
    }
}
