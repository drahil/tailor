<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH\SessionCommands;

use drahil\Tailor\Support\Validation\ValidationException;
use drahil\Tailor\Support\ValueObjects\SessionTags;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SessionListCommand extends SessionCommand
{
    protected function configure(): void
    {
        $this
            ->setName('session:list')
            ->setAliases(['sessions'])
            ->setDescription('List all saved sessions')
            ->setHelp(<<<HELP
  Display a list of all saved Tailor sessions.

  Usage:
    session:list                           List all sessions
    session:list --tag=api                 Filter by single tag
    session:list --tag=api --tag=debug    Filter by multiple tags (all must match)

  Examples:
    >>> session:list
    >>> sessions --tag=testing
    >>> sessions -t api -t debug
  HELP
)
            ->addOption(
                'tag',
                't',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Filter sessions by tags (all tags must match)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sessionManager = $this->getSessionManager();
        $filterTags = $input->getOption('tag') ?? [];

        try {
            $tags = new SessionTags($filterTags);
        } catch (ValidationException $e) {
            $output->writeln("<error>Invalid tag filter: {$e->result->firstError()}</error>");
            return 1;
        }

        $sessions = $sessionManager->list($tags->toArray());

        if (empty($sessions)) {
            $message = ! $tags->isEmpty()
                ? '<comment>No sessions found with tags: ' . implode(', ', $tags->toArray()) . '</comment>'
                : '<comment>No saved sessions found.</comment>';
            $output->writeln($message);
            return 0;
        }

        $output->writeln('<info>Saved Sessions:</info>');
        $output->writeln('');

        foreach ($sessions as $session) {
            $tagsDisplay = ! empty($session['tags'])
                ? ' <fg=gray>[' . implode(', ', $session['tags']) . ']</>'
                : '';

            $output->writeln(sprintf(
                '  <fg=cyan>%s</> - %s (%d commands)%s',
                $session['name'],
                $session['created_at'],
                $session['command_count'],
                $tagsDisplay
            ));
        }

        return 0;
    }
}
