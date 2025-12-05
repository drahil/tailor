<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH;

use drahil\Tailor\Support\Formatting\SessionOutputFormatter;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SessionExecuteCommand extends SessionCommand
{
    public function __construct(
        private readonly SessionOutputFormatter $formatter
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
        $shell = $this->getApplication();
        $nameInput = $input->getArgument('name');

        $sessionName = $this->validateSessionName($nameInput, $output);
        if (! $sessionName) {
            return 1;
        }

        try {
            $sessionData = $sessionManager->load($sessionName);

            $this->formatter->displayExecutionHeader($output, $sessionData);

            $executedCount = 0;
            $failedCount = 0;

            foreach ($sessionData->commands as $command) {
                $code = $this->decodeCommandCode($command['code']);

                if ($code === '_HiStOrY_V2_') {
                    continue;
                }

                try {
                    $output->writeln("<comment>>>> {$code}</comment>");

                    $result = $shell->execute($code);

                    if ($result !== null) {
                        $shell->writeReturnValue($result);
                    }

                    $executedCount++;
                } catch (Exception $e) {
                    $failedCount++;
                    $output->writeln("<error>Failed to execute: {$e->getMessage()}</error>");
                }
            }

            $output->writeln('');
            $output->writeln("<info>Executed {$executedCount} command(s)" .
                ($failedCount > 0 ? " ({$failedCount} failed)" : "") . "</info>");

            return 0;

        } catch (Exception $e) {
            return $this->operationFailedError($output, 'load', $e->getMessage());
        }
    }
}
