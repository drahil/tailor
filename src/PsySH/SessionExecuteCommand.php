<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH;

use drahil\Tailor\Support\Validation\ValidationException;
use drahil\Tailor\Support\ValueObjects\SessionName;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SessionExecuteCommand extends SessionCommand
{
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

        try {
            $sessionName = SessionName::fromNullable($nameInput);
            if (! $sessionName) {
                $output->writeln('<error>Name of the session must be provided.</error>');
                return 1;
            }
        } catch (ValidationException $e) {
            $output->writeln("<error>{$e->result->firstError()}</error>");
            return 1;
        }

        try {
            $sessionData = $sessionManager->load($sessionName);

            $commandCount = $sessionData->getCommandCount();

            $output->writeln('');
            $output->writeln("<info>✓ Executing session...</info>");
            $output->writeln('');
            $output->writeln("  <fg=cyan>Name:</>        {$sessionData->metadata->name}");
            $output->writeln("  <fg=cyan>Commands:</>    {$commandCount}");

            if ($sessionData->metadata->hasDescription()) {
                $output->writeln("  <fg=cyan>Description:</> {$sessionData->metadata->description}");
            }

            $output->writeln('');

            $executedCount = 0;
            $failedCount = 0;

            foreach ($sessionData->commands as $command) {
                $code = $command['code'];

                if (str_contains($code, '\\0') || str_contains($code, '\\1')) {
                    $code = stripcslashes($code);
                } else {
                    $decoded = urldecode($code);
                    if ($decoded !== $code) {
                        $code = $decoded;
                    }
                }

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
