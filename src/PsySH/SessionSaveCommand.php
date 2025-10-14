<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH;

use drahil\Tailor\Support\SessionTracker;
use Exception;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SessionSaveCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('session:save')
            ->setAliases(['save'])
            ->setDescription('Save the current session')
            ->setHelp(
                <<<HELP
  Save the current Tailor session with all executed commands.

  Usage:
    session:save my-work                    Save session with a name
    session:save my-work --force            Overwrite existing session
    session:save my-work -d "Description"   Save with description
    session:save                            Auto-generate name from timestamp

  Examples:
    >>> session:save testing-facebook-api
    >>> session:save redis-test --force
    >>> session:save api-debugging -d "Testing API endpoints"
  HELP
            )
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'Name for the session (auto-generated if not provided)'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite existing session without confirmation'
            )
            ->addOption(
                'description',
                'd',
                InputOption::VALUE_REQUIRED,
                'Description for the session'
            )
            ->addOption(
                'tags',
                't',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Tags for organizing sessions'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sessionManager = $this->getApplication()->getScopeVariable('__sessionManager');
        $sessionTracker = $this->getApplication()->getScopeVariable('__sessionTracker');

        $this->captureHistoryToTracker($sessionTracker);

        if ($sessionTracker->getCommandCount() === 0) {
            $output->writeln('<comment>No commands to save. Execute some commands first.</comment>');
            return 1;
        }

        $name = $input->getArgument('name');
        if (! $name) {
            $name = 'session-' . date('Y-m-d-His');
            $output->writeln("<comment>Auto-generated session name: {$name}</comment>");
        }

        if (! $this->isValidSessionName($name)) {
            $output
                ->writeln(
                    '<error>Invalid session name. Use only alphanumeric characters, hyphens, and underscores.</error>'
                );
            return 1;
        }

        $force = $input->getOption('force');
        if ($sessionManager->exists($name) && ! $force) {
            $output->writeln("<comment>Session '{$name}' already exists.</comment>");

            if (! $this->confirmOverwrite($input, $output)) {
                $output->writeln('<info>Save cancelled.</info>');
                return 0;
            }
        }

        $metadata = [
            'description' => $input->getOption('description'),
            'tags' => $input->getOption('tags') ?? [],
        ];

        try {
            $sessionManager->save($name, $sessionTracker, $metadata);

            $commandCount = $sessionTracker->getCommandCount();
            $output->writeln('');
            $output->writeln("<info>✓ Session saved successfully!</info>");
            $output->writeln('');
            $output->writeln("  <fg=cyan>Name:</>        {$name}");
            $output->writeln("  <fg=cyan>Commands:</>    {$commandCount}");

            if ($metadata['description']) {
                $output->writeln("  <fg=cyan>Description:</> {$metadata['description']}");
            }

            if (! empty($metadata['tags'])) {
                $output->writeln("  <fg=cyan>Tags:</>        " . implode(', ', $metadata['tags']));
            }

            $output->writeln('');
            $output->writeln("<comment>Load this session later with:</comment> session:load {$name}");

            return 0;

        } catch (Exception $e) {
            $output->writeln("<error>Failed to save session: {$e->getMessage()}</error>");
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

    /**
     * Ask user for confirmation to overwrite
     */
    protected function confirmOverwrite(InputInterface $input, OutputInterface $output): bool
    {
        $output->write('<question>Overwrite existing session? [y/N]</question> ');

        $handle = fopen('php://stdin', 'r');
        $line = fgets($handle);
        fclose($handle);

        $answer = trim(strtolower($line));

        return in_array($answer, ['y', 'yes'], true);
    }

    /**
     * Capture commands from PsySH history and add them to SessionTracker
     */
    protected function captureHistoryToTracker(SessionTracker $tracker): void
    {
        $tracker->clear();

        $historyFile = storage_path('tailor/tailor_history');

        if (! file_exists($historyFile)) {
            return;
        }

        $lines = file($historyFile, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        $sessionStartLine = $tracker->getSessionStartLine();

        $currentSessionLines = array_slice($lines, $sessionStartLine);

        foreach ($currentSessionLines as $entry) {
            $entry = trim($entry);

            if (empty($entry)) {
                continue;
            }

            if ($this->shouldSkipCommand($entry)) {
                continue;
            }

            $tracker->addCommand($entry);
        }
    }

    /**
     * Check if a command should be skipped (internal commands)
     */
    protected function shouldSkipCommand(string $command): bool
    {
        $skipPatterns = [
            '/^session:/',
            '/^help\b/',
            '/^exit\b/',
            '/^quit\b/',
            '/^history\b/',
            '/^clear\b/',
        ];

        foreach ($skipPatterns as $pattern) {
            if (preg_match($pattern, trim($command))) {
                return true;
            }
        }

        return false;
    }
}
