<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH;

use drahil\Tailor\Support\DTOs\SessionData;
use drahil\Tailor\Support\VariableFormatter;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SessionViewCommand extends SessionCommand
{
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

            $output->writeln('');
            $output->writeln("<info>Session: {$sessionData->metadata->name}</info>");
            $output->writeln('');

            $this->displayMetadata($output, $sessionData);
            $output->writeln('');

            $this->displayCommands($output, $sessionData);
            $output->writeln('');

            if ($sessionData->hasVariables() && count($sessionData->variables) > 0) {
                $this->displayVariables($output, $sessionData);
                $output->writeln('');
            }

            return 0;

        } catch (Exception $e) {
            return $this->operationFailedError($output, 'view', $e->getMessage());
        }
    }

    protected function displayMetadata(OutputInterface $output, SessionData $sessionData): void
    {
        $metadata = $sessionData->metadata;
        $sessionMeta = $sessionData->sessionMetadata;

        $output->writeln('<fg=yellow>Metadata:</>');
        $output->writeln('');

        if ($metadata->hasDescription()) {
            $output->writeln("  <fg=cyan>Description:</>  {$metadata->description}");
        }

        if ($metadata->hasTags()) {
            $output->writeln("  <fg=cyan>Tags:</>         " . implode(', ', $metadata->tags));
        }

        $output->writeln("  <fg=cyan>Commands:</>     {$sessionData->getCommandCount()}");

        if (isset($sessionMeta['project_path'])) {
            $output->writeln("  <fg=cyan>Project:</>      {$sessionMeta['project_path']}");
        }

        if ($metadata->createdAt) {
            $output->writeln("  <fg=cyan>Created:</>      {$metadata->createdAt->format('Y-m-d H:i:s')}");
        }

        if ($metadata->updatedAt) {
            $output->writeln("  <fg=cyan>Updated:</>      {$metadata->updatedAt->format('Y-m-d H:i:s')}");
        }

        if ($metadata->laravelVersion) {
            $output->writeln("  <fg=cyan>Laravel:</>      {$metadata->laravelVersion}");
        }

        if ($metadata->phpVersion) {
            $output->writeln("  <fg=cyan>PHP:</>          {$metadata->phpVersion}");
        }

        if (isset($sessionMeta['duration_seconds'])) {
            $duration = round($sessionMeta['duration_seconds'], 2);
            $output->writeln("  <fg=cyan>Duration:</>     {$duration}s");
        }
    }

    protected function displayCommands(OutputInterface $output, SessionData $sessionData): void
    {
        $commandCount = $sessionData->getCommandCount();
        $output->writeln("<fg=yellow>Commands:</> <fg=gray>({$commandCount} total)</>");
        $output->writeln('');

        if (! $sessionData->hasCommands()) {
            $output->writeln('  <comment>No commands recorded</comment>');
            return;
        }

        $output->writeln('<fg=gray>─────────────────────────────────────────────────────────────────────────────────</>');

        foreach ($sessionData->commands as $command) {
            $code = $this->decodeCommandCode($command['code']);

            if ($code === '_HiStOrY_V2_') {
                continue;
            }

            $output->writeln($code);
        }

        $output->writeln('<fg=gray>─────────────────────────────────────────────────────────────────────────────────</>');
    }

    protected function displayVariables(OutputInterface $output, $sessionData): void
    {
        $output->writeln('<fg=yellow>Variables:</>');
        $output->writeln('');

        foreach ($sessionData->variables as $name => $value) {
            $valueStr = VariableFormatter::format($value);
            $output->writeln("  <fg=cyan>\${$name}</>  =  {$valueStr}");
        }
    }
}
