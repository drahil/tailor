<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH;

use DateTime;
use drahil\Tailor\Support\DTOs\SessionData;
use drahil\Tailor\Support\DTOs\SessionMetadata;
use drahil\Tailor\Support\HistoryCaptureService;
use drahil\Tailor\Support\Validation\ValidationException;
use drahil\Tailor\Support\ValueObjects\SessionDescription;
use drahil\Tailor\Support\ValueObjects\SessionName;
use Exception;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SessionUpdateCommand extends SessionCommand
{
    public function __construct(
        private readonly HistoryCaptureService $historyCaptureService
    ) {
        parent::__construct();
    }

    /**
     * Configure the command.
     */
    protected function configure(): void
    {
        $this
            ->setName('session:update')
            ->setDescription('Update the currently loaded session with new commands')
            ->setAliases(['update'])
            ->setHelp(<<<HELP
  Update the currently loaded session with new commands executed in this session.

  This command is only available when a session has been loaded via:
    php artisan tailor --session=my-work

  Usage:
    session:update                          Update loaded session
    session:update -d "New description"     Update with new description
    session:update -t tag1 -t tag2          Update with new tags

  Examples:
    >>> session:update
    >>> session:update -d "Updated API testing session"
  HELP
)
            ->addOption(
                'description',
                'd',
                InputOption::VALUE_REQUIRED,
                'Update the session description'
            )
            ->addOption(
                'tags',
                't',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Update the session tags'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sessionManager = $this->getSessionManager();
        $sessionTracker = $this->getApplication()->getScopeVariable('__sessionTracker');

        if (! $sessionTracker->hasLoadedSession()) {
            $output->writeln('<error>No session is currently loaded.</error>');
            $output->writeln('<comment>Load a session using: php artisan tailor --session=my-work</comment>');
            return 1;
        }

        $loadedSessionName = $sessionTracker->getLoadedSessionName();

        try {
            $sessionName = SessionName::from($loadedSessionName);
        } catch (ValidationException $e) {
            $output->writeln("<error>{$e->result->firstError()}</error>");
            return 1;
        }

        try {
            $existingSession = $sessionManager->load($sessionName);
        } catch (Exception $e) {
            return $this->operationFailedError($output, 'load', $e->getMessage());
        }

        $historyFile = Storage::disk('local')->path('tailor/tailor_history');
        $sessionStartLine = $sessionTracker->getSessionStartLine();

        $newHistoryCommands = $this->historyCaptureService->captureFromLine($historyFile, $sessionStartLine);

        $newHistoryCommands = array_filter($newHistoryCommands, function ($cmd) {
            return ! str_contains($cmd, 'session:update') && ! str_contains($cmd, 'update');
        });

        $newCommands = array_values($newHistoryCommands);

        if (empty($newCommands)) {
            $output->writeln('<comment>No new commands to add to the session.</comment>');
            return 0;
        }

        $formattedNewCommands = [];
        $baseOrder = count($existingSession->commands);
        foreach ($newCommands as $index => $code) {
            $formattedNewCommands[] = [
                'code' => $code,
                'output' => null,
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'order' => $baseOrder + $index + 1,
            ];
        }

        $allCommands = array_merge($existingSession->commands, $formattedNewCommands);

        $description = $input->getOption('description')
            ? SessionDescription::fromNullable($input->getOption('description'))
            : $existingSession->metadata->description;

        $tags = $input->getOption('tags')
            ? $input->getOption('tags')
            : $existingSession->metadata->tags;

        $metadata = new SessionMetadata(
            name: $sessionName,
            description: $description,
            tags: $tags,
        );

        $updatedSessionData = new SessionData(
            metadata: $metadata,
            commands: $allCommands,
            variables: $existingSession->variables,
            sessionMetadata: $existingSession->sessionMetadata
        );

        try {
            $sessionManager->saveSessionData($updatedSessionData);

            $newCommandCount = count($formattedNewCommands);
            $totalCommandCount = count($allCommands);

            $output->writeln("<info>✓ Session '{$sessionName}' updated successfully!</info>");
            $output->writeln("  <comment>Added {$newCommandCount} new command(s)</comment>");
            $output->writeln("  <comment>Total commands: {$totalCommandCount}</comment>");

            return 0;

        } catch (Exception $e) {
            return $this->operationFailedError($output, 'update', $e->getMessage());
        }
    }
}
