<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH;

use Exception;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SessionLoadCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('session:load')
            ->setAliases(['load'])
            ->setDescription('Load a previously saved Tailor session')
            ->setHelp(
                <<<HELP
  Load a previously saved Tailor session by name.
  You can list available sessions with the 'session:list' command.

  Usage:
    session:load my-session                   Load the session named 'my-session'
  Examples:
    >>> session:load my-session
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
        $sessionManager = $this->getApplication()->getScopeVariable('__sessionManager');
        $shell = $this->getApplication();

        $name = $input->getArgument('name');
        if (! $name) {
            $output
                ->writeln(
                    '<error>Name of the session must be provided.</error>'
                );

            return 1;
        }

        if (! $this->isValidSessionName($name)) {
            $output
                ->writeln(
                    '<error>Invalid session name. Use only alphanumeric characters, hyphens, and underscores.</error>'
                );
            return 1;
        }

        try {
            $sessionData = $sessionManager->load($name);

            $commandCount = count($sessionData['commands'] ?? []);

            $output->writeln('');
            $output->writeln("<info>✓ Session loaded successfully!</info>");
            $output->writeln('');
            $output->writeln("  <fg=cyan>Name:</>        {$name}");
            $output->writeln("  <fg=cyan>Commands:</>    {$commandCount}");

            if (!empty($sessionData['description'])) {
                $output->writeln("  <fg=cyan>Description:</> {$sessionData['description']}");
            }

            $output->writeln('');

            $executedCount = 0;
            $failedCount = 0;

            foreach ($sessionData['commands'] ?? [] as $command) {
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
            $output->writeln("<error>Failed to load session: {$e->getMessage()}</error>");
            return 1;
        }
    }

    /**
     * Validate session name format
     */
    protected function isValidSessionName(string $name): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $name) === 1;
    }
}
