<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH\SessionCommands;

use drahil\Tailor\Services\HistoryCaptureService;
use drahil\Tailor\Support\DTOs\SessionData;
use drahil\Tailor\Support\Formatting\SessionOutputFormatter;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class SessionExecuteCommand extends SessionCommand
{
    public function __construct(
        private readonly HistoryCaptureService $historyCaptureService,
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
  You can list available sessions with the 'session:ls' command.

  In production, sessions without the 'safe-for-prod' tag ask for confirmation first.

  Usage:
    session:execute my-session                Execute the session named 'my-session'
    session:execute my-session --force        Execute in production without confirmation
  Examples:
    >>> session:execute my-session
  HELP
            )
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'Name of the session'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Execute in production without confirmation'
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

            if (! $this->confirmProductionRun($input, $output, $sessionData)) {
                $output->writeln('<info>Execution cancelled.</info>');
                return 0;
            }

            $this->formatter->displayExecutionHeader($output, $sessionData);

            $total = count($sessionData->commands);
            $attempted = 0;
            $failed = false;

            foreach ($sessionData->commands as $code) {
                $attempted++;

                try {
                    $output->writeln("<comment>>>> {$code}</comment>");

                    $result = $shell->execute($code, true);

                    if ($result !== null) {
                        $shell->writeReturnValue($result);
                    }
                } catch (Throwable $e) {
                    $failed = true;
                    $output->writeln("<error>Failed to execute: {$e->getMessage()}</error>");
                    break;
                }
            }

            $output->writeln('');
            $output->writeln($failed
                ? "<error>Stopped after {$attempted} of {$total} command(s); " . ($total - $attempted) . " not run.</error>"
                : "<info>Executed {$total} command(s)</info>");

            $sessionTracker = $shell->getScopeVariable('__sessionTracker');
            $this->historyCaptureService->markAppendStart($sessionTracker, storage_path('tailor/tailor_history'));
            $sessionTracker->setActiveSession($sessionName->toString());

            $output->writeln($failed
                ? "<comment>'{$sessionName}' is active, but replay stopped at a failed command. session:append will add new commands after the whole session.</comment>"
                : "<comment>'{$sessionName}' is active. Use session:append to add new commands to it.</comment>");

            return $failed ? 1 : 0;

        } catch (Exception $e) {
            return $this->operationFailedError($output, 'load', $e->getMessage());
        }
    }

    /**
     * Ask before running a session in production unless it is tagged as safe for production.
     */
    private function confirmProductionRun(InputInterface $input, OutputInterface $output, SessionData $sessionData): bool
    {
        $safeTag = strtolower(config('tailor.production.safe_tag', 'safe-for-prod'));
        $isSafe = in_array($safeTag, array_map('strtolower', $sessionData->metadata->tags), true);

        if (config('app.env') !== 'production' || $isSafe || $input->getOption('force')) {
            return true;
        }

        $output->writeln("<comment>You are in production and '{$sessionData->metadata->name}' is not tagged '{$safeTag}'.</comment>");
        $output->writeln('');
        $this->formatter->displayCommands($output, $sessionData);
        $output->writeln('');

        return $this->confirm($input, $output, 'Execute it anyway?');
    }
}
