<?php

declare(strict_types=1);

namespace drahil\Tailor\Support\Formatting;

use drahil\Tailor\Support\DTOs\SessionData;
use drahil\Tailor\Services\VariableFormatter;
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

    /**
     * Format and display session commands section.
     *
     * @param OutputInterface $output
     * @param SessionData $sessionData
     * @param callable|null $codeDecoder Optional decoder for command code
     * @return void
     */
    public function displayCommands(
        OutputInterface $output,
        SessionData $sessionData,
        ?callable $codeDecoder = null
    ): void {
        $commandCount = $sessionData->getCommandCount();
        $output->writeln("<fg=yellow>Commands:</> <fg=gray>({$commandCount} total)</>");
        $output->writeln('');

        if (! $sessionData->hasCommands()) {
            $output->writeln('  <comment>No commands recorded</comment>');
            return;
        }

        $output->writeln('<fg=gray>─────────────────────────────────────────────────────────────────────────────────</>');

        foreach ($sessionData->commands as $command) {
            $code = $codeDecoder ? $codeDecoder($command['code']) : $command['code'];

            if ($code === '_HiStOrY_V2_') {
                continue;
            }

            $output->writeln($code);
        }

        $output->writeln('<fg=gray>─────────────────────────────────────────────────────────────────────────────────</>');
    }

    /**
     * Format and display session variables section.
     *
     * @param OutputInterface $output
     * @param array<string, array{type: string, class: string|null, value: string|null}> $variables
     * @return void
     */
    public function displayVariables(OutputInterface $output, array $variables): void
    {
        $output->writeln('<fg=yellow>Variables:</>');
        $output->writeln('');

        if (empty($variables)) {
            $output->writeln('  <comment>No variables saved</comment>');
            return;
        }

        foreach ($variables as $name => $value) {
            $valueStr = VariableFormatter::format($value);
            $output->writeln("  <fg=cyan>\${$name}</>  =  {$valueStr}");
        }
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
