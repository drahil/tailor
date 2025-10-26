<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH;

use drahil\Tailor\Support\Formatting\SessionOutputFormatter;
use drahil\Tailor\Support\SessionCommandRunner;
use Exception;
use Psy\Shell;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SessionExecuteCommand extends SessionCommand
{
    public function __construct(
        private readonly SessionOutputFormatter $formatter,
        private readonly SessionCommandRunner $runner
    ) {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this
            ->setName('session:execute')
            ->setAliases(['exec'])
            ->setDescription('Execute a previously saved Tailor session')
            ->setHelp(
                <<<HELP
  Execute a previously saved Tailor session by name.
  This will load and automatically run all commands in the session.
  You can list available sessions with the 'session:list' command.

  Usage:
    session:execute my-session                Execute the session named 'my-session'
  Examples:
    >>> session:execute my-session
  HELP
            )
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'Name of the session'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sessionManager = $this->getSessionManager();
        $application = $this->getApplication();

        if (! $application instanceof Shell) {
            throw new \RuntimeException('This command must be run within a PsySH shell.');
        }

        $shell = $application;
        $sessionTracker = $shell->getScopeVariable('__sessionTracker');
        $nameInput = $input->getArgument('name');

        $sessionName = $this->validateSessionName($nameInput, $output);
        if (! $sessionName) {
            return 1;
        }

        try {
            $sessionData = $sessionManager->load($sessionName);

            $this->formatter->displayExecutionHeader($output, $sessionData);

            $this->runner->executeWithSummary($shell, $sessionData, $sessionTracker, $output);

            return 0;

        } catch (Exception $e) {
            return $this->operationFailedError($output, 'load', $e->getMessage());
        }
    }
}
