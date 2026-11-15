<?php

declare(strict_types=1);

namespace drahil\Tailor\Support\Formatting;

use drahil\Tailor\Support\DTOs\SessionData;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Formats session output for console display.
 *
 * Centralizes all session-related output formatting to ensure
 * consistent presentation across commands.
 */
final class SessionOutputFormatter
{
    /**
     * Format and display session metadata section.
     *
     * @param OutputInterface $output
     * @param SessionData $sessionData
     * @return void
     */
    public function displayMetadata(OutputInterface $output, SessionData $sessionData): void
    {
        $metadata = $sessionData->metadata;

        $output->writeln('<fg=yellow>Metadata:</>');
        $output->writeln('');

        if ($metadata->hasDescription()) {
            $output->writeln("  <fg=cyan>Description:</>  {$metadata->description}");
        }

        if ($metadata->hasTags()) {
            $output->writeln("  <fg=cyan>Tags:</>         " . implode(', ', $metadata->tags));
        }

        $output->writeln("  <fg=cyan>Commands:</>     {$sessionData->getCommandCount()}");

        if ($metadata->createdAt) {
            $output->writeln("  <fg=cyan>Created:</>      {$metadata->createdAt->format('Y-m-d H:i:s')}");
        }

        if ($metadata->updatedAt) {
            $output->writeln("  <fg=cyan>Updated:</>      {$metadata->updatedAt->format('Y-m-d H:i:s')}");
        }
    }

    /**
     * Format and display session commands section.
     *
     * @param OutputInterface $output
     * @param SessionData $sessionData
     * @return void
     */
    public function displayCommands(OutputInterface $output, SessionData $sessionData): void
    {
        $commandCount = $sessionData->getCommandCount();
        $output->writeln("<fg=yellow>Commands:</> <fg=gray>({$commandCount} total)</>");
        $output->writeln('');

        if (! $sessionData->hasCommands()) {
            $output->writeln('  <comment>No commands recorded</comment>');
            return;
        }

        $output->writeln('<fg=gray>─────────────────────────────────────────────────────────────────────────────────</>');

        foreach ($sessionData->commands as $code) {
            $output->writeln($code);
        }

        $output->writeln('<fg=gray>─────────────────────────────────────────────────────────────────────────────────</>');
    }

    /**
     * Format and display session summary (used after save operations).
     *
     * @param OutputInterface $output
     * @param string $sessionName
     * @param int $commandCount
     * @param string|null $description
     * @param array<int, string> $tags
     * @return void
     */
    public function displaySaveSummary(
        OutputInterface $output,
        string $sessionName,
        int $commandCount,
        ?string $description = null,
        array $tags = []
    ): void {
        $output->writeln('');
        $output->writeln("<info>✓ Session saved successfully!</info>");
        $output->writeln('');
        $output->writeln("  <fg=cyan>Name:</>        {$sessionName}");
        $output->writeln("  <fg=cyan>Commands:</>    {$commandCount}");

        if ($description) {
            $output->writeln("  <fg=cyan>Description:</> {$description}");
        }

        if (! empty($tags)) {
            $output->writeln("  <fg=cyan>Tags:</>        " . implode(', ', $tags));
        }

        $output->writeln('');
    }

    /**
     * Format and display session execution header.
     *
     * @param OutputInterface $output
     * @param SessionData $sessionData
     * @return void
     */
    public function displayExecutionHeader(OutputInterface $output, SessionData $sessionData): void
    {
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
    }
}
