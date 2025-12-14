<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH\SessionCommands;

use drahil\Tailor\Support\DTOs\SessionData;
use drahil\Tailor\Support\Formatting\SessionOutputFormatter;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SessionViewCommand extends SessionCommand
{
    public function __construct(
        private readonly SessionOutputFormatter $formatter
    ) {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this
            ->setName('session:view')
            ->setDescription('View details and commands of a saved session')
            ->setAliases(['view'])
            ->setHelp(<<<HELP
  View detailed information about a saved Tailor session including
  all commands, metadata, and session statistics.

  Usage:
    session:view my-session                 View session details

  Examples:
    >>> session:view testing-facebook-api
    >>> session:view api-debugging
  HELP
)
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Name of the session to view'
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

        try {
            $sessionData = $sessionManager->load($sessionName);

            $this->displaySessionData($sessionData, $output);

            return 0;

        } catch (Exception $e) {
            return $this->operationFailedError($output, 'view', $e->getMessage());
        }
    }

    private function displaySessionData(SessionData $sessionData, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln("<info>Session: {$sessionData->metadata->name}</info>");
        $output->writeln('');

        $this->formatter->displayMetadata($output, $sessionData);
        $output->writeln('');

        $this->formatter->displayCommands($output, $sessionData, fn($code) => $this->decodeCommandCode($code));
        $output->writeln('');

        if ($sessionData->hasVariables() && count($sessionData->variables) > 0) {
            $this->formatter->displayVariables($output, $sessionData->variables);
            $output->writeln('');
        }
    }
}
