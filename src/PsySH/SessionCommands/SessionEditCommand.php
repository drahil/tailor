<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH\SessionCommands;

use DateTime;
use drahil\Tailor\Support\DTOs\SessionData;
use drahil\Tailor\Support\DTOs\SessionMetadata;
use drahil\Tailor\Support\Validation\ValidationException;
use drahil\Tailor\Support\ValueObjects\SessionDescription;
use drahil\Tailor\Support\ValueObjects\SessionTags;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SessionEditCommand extends SessionCommand
{
    protected function configure(): void
    {
        $this
            ->setName('session:edit')
            ->setDescription('Edit session metadata')
            ->setAliases(['edit'])
            ->setHelp(<<<HELP
  Edit metadata of an existing session including description and tags.

  Usage:
    session:edit my-work -d "New description"      Update description
    session:edit my-work --add-tag api             Add a tag
    session:edit my-work --remove-tag old          Remove a tag
    session:edit my-work --set-tags api,debug      Replace all tags

  Examples:
    >>> session:edit testing-api -d "Testing REST API endpoints"
    >>> session:edit my-session --add-tag debug --add-tag api
    >>> session:edit old-session --remove-tag deprecated
    >>> session:edit session-name --set-tags production,critical
  HELP
)
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Name of the session to edit'
            )
            ->addOption(
                'description',
                'd',
                InputOption::VALUE_REQUIRED,
                'Update session description'
            )
            ->addOption(
                'add-tag',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Add tag(s) to the session'
            )
            ->addOption(
                'remove-tag',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Remove tag(s) from the session'
            )
            ->addOption(
                'set-tags',
                't',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Replace all tags with specified tags'
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
            $metadata = $sessionData->metadata;

            $hasChanges = false;

            $newDescription = $metadata->description;
            if ($input->getOption('description') !== null) {
                $newDescription = SessionDescription::fromNullable($input->getOption('description'));
                $hasChanges = true;
            }

            $newTags = $this->calculateNewTags(
                $metadata->tags,
                $input->getOption('set-tags'),
                $input->getOption('add-tag'),
                $input->getOption('remove-tag')
            );

            if ($newTags !== $metadata->tags) {
                $hasChanges = true;
            }

            if (! $hasChanges) {
                $output->writeln('<comment>No changes specified. Use --description, --add-tag, --remove-tag, or --set-tags.</comment>');
                return 0;
            }

            $tags = new SessionTags($newTags);

            $updatedMetadata = new SessionMetadata(
                name: $metadata->name,
                description: $newDescription,
                tags: $tags->toArray(),
                createdAt: $metadata->createdAt,
                updatedAt: new DateTime(),
                laravelVersion: $metadata->laravelVersion,
                phpVersion: $metadata->phpVersion,
            );

            $updatedSessionData = new SessionData(
                metadata: $updatedMetadata,
                commands: $sessionData->commands,
                variables: $sessionData->variables,
                sessionMetadata: $sessionData->sessionMetadata
            );

            $sessionManager->update($updatedSessionData);

            $output->writeln('');
            $output->writeln('<info>✓ Session updated successfully!</info>');
            $output->writeln('');
            $output->writeln("  <fg=cyan>Name:</>        {$metadata->name}");

            if ($newDescription) {
                $output->writeln("  <fg=cyan>Description:</> {$newDescription}");
            }

            if (! empty($tags->toArray())) {
                $output->writeln("  <fg=cyan>Tags:</>        " . implode(', ', $tags->toArray()));
            }

            $output->writeln('');

            return 0;

        } catch (ValidationException $e) {
            $output->writeln("<error>{$e->result->firstError()}</error>");
            return 1;
        } catch (Exception $e) {
            return $this->operationFailedError($output, 'edit', $e->getMessage());
        }
    }

    /**
     * Calculate new tags based on provided options.
     *
     * @param array<string> $currentTags
     * @param array<string>|null $setTags
     * @param array<string>|null $addTags
     * @param array<string>|null $removeTags
     * @return array<string>
     */
    private function calculateNewTags(
        array $currentTags,
        ?array $setTags,
        ?array $addTags,
        ?array $removeTags
    ): array {
        if ($setTags !== null && count($setTags) > 0) {
            return $setTags;
        }

        $tags = $currentTags;

        if ($addTags !== null && count($addTags) > 0) {
            $tags = array_unique(array_merge($tags, $addTags));
        }

        if ($removeTags !== null && count($removeTags) > 0) {
            $tags = array_values(array_diff($tags, $removeTags));
        }

        return $tags;
    }
}
