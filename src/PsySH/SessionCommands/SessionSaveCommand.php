<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH\SessionCommands;

use drahil\Tailor\Services\HistoryCaptureService;
use drahil\Tailor\Support\DTOs\SessionMetadata;
use drahil\Tailor\Support\Formatting\SessionOutputFormatter;
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
    public function __construct(
        private readonly HistoryCaptureService $historyCaptureService,
        private readonly SessionOutputFormatter $formatter
    ) {
        parent::__construct();
    }
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

        $historyFile = storage_path('tailor/tailor_history');
        $this->historyCaptureService->captureHistoryToTracker($sessionTracker, $historyFile);

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

            $this->formatter->displaySaveSummary(
                $output,
                $sessionName->toString(),
                $sessionTracker->getCommandCount(),
                $metadata->description?->toString(),
                $metadata->tags
            );

            return 0;

        } catch (Exception $e) {
            return $this->operationFailedError($output, 'save', $e->getMessage());
        }
    }
}
