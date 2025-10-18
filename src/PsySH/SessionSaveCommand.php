<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH;

use drahil\Tailor\Support\DTOs\SessionMetadata;
use drahil\Tailor\Support\SessionTracker;
use drahil\Tailor\Support\Validation\ValidationException;
use drahil\Tailor\Support\ValueObjects\SessionDescription;
use drahil\Tailor\Support\ValueObjects\SessionName;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SessionSaveCommand extends SessionCommand
{
    /**
     * Configure the command.
     */
    protected function configure(): void
    {
        $this
            ->setName('session:save')
            ->setDescription('Save the current session')
            ->setAliases(['save'])
            ->setHelp(<<<HELP
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
        $sessionManager = $this->getSessionManager();
        $sessionTracker = $this->getApplication()->getScopeVariable('__sessionTracker');

        $this->captureHistoryToTracker($sessionTracker);

        if ($sessionTracker->getCommandCount() === 0) {
            $output->writeln('<comment>No commands to save. Execute some commands first.</comment>');
            return 1;
        }

        $nameInput = $input->getArgument('name');
        if (! $nameInput) {
            $nameInput = 'session-' . date('Y-m-d-His');
            $output->writeln("<comment>Auto-generated session name: {$nameInput}</comment>");
        }

        try {
            $sessionName = SessionName::from($nameInput);
        } catch (ValidationException $e) {
            $output->writeln("<error>{$e->result->firstError()}</error>");
            return 1;
        }

        $force = $input->getOption('force');
        if ($this->sessionExists($sessionName->toString()) && ! $force) {
            $output->writeln("<comment>Session '{$sessionName}' already exists.</comment>");

            if (! $this->confirm($output, 'Overwrite existing session?')) {
                $output->writeln('<info>Save cancelled.</info>');
                return 0;
            }
        }

        $metadata = new SessionMetadata(
            name: $sessionName,
            description: SessionDescription::fromNullable($input->getOption('description')),
            tags: $input->getOption('tags') ?? [],
        );

        try {
            $sessionManager->save($metadata, $sessionTracker);

            $commandCount = $sessionTracker->getCommandCount();
            $output->writeln('');
            $output->writeln("<info>✓ Session saved successfully!</info>");
            $output->writeln('');
            $output->writeln("  <fg=cyan>Name:</>        {$sessionName}");
            $output->writeln("  <fg=cyan>Commands:</>    {$commandCount}");

            if ($metadata->hasDescription()) {
                $output->writeln("  <fg=cyan>Description:</> {$metadata->description}");
            }

            if ($metadata->hasTags()) {
                $output->writeln("  <fg=cyan>Tags:</>        " . implode(', ', $metadata->tags));
            }

            $output->writeln('');

            return 0;

        } catch (Exception $e) {
            return $this->operationFailedError($output, 'save', $e->getMessage());
        }
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
