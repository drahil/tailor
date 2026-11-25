<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH\SessionCommands;

use drahil\Tailor\Services\SessionExporter;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SessionExportCommand extends SessionCommand
{
    public function __construct(
        private readonly SessionExporter $exporter
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('session:export')
            ->setAliases(['export'])
            ->setDescription('Export a saved session as a PHP script or an Artisan command')
            ->setHelp(<<<HELP
  Export a saved session to a file you can use outside Tailor.

  Formats:
    script     Plain PHP file, run it with: php artisan tinker <file>
    command    Artisan command class, run it with: php artisan tailor:<session>

  Usage:
    session:export blog-stats                     Export as a script
    session:export blog-stats --format=command    Export as an Artisan command
    session:export blog-stats --path=app/Scripts  Write to another folder
    session:export blog-stats --force             Overwrite an existing file
  HELP
            )
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'Name of the session'
            )
            ->addOption(
                'format',
                null,
                InputOption::VALUE_REQUIRED,
                'Export format: ' . implode(', ', SessionExporter::FORMATS)
            )
            ->addOption(
                'path',
                'p',
                InputOption::VALUE_REQUIRED,
                'Folder to write the file to'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite an existing file'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sessionName = $this->validateSessionName($input->getArgument('name'), $output);
        if (! $sessionName) {
            return 1;
        }

        $format = $input->getOption('format') ?? config('tailor.export.default_format');
        if (! in_array($format, SessionExporter::FORMATS, true)) {
            $output->writeln("<error>Unknown format '{$format}'. Use one of: " . implode(', ', SessionExporter::FORMATS) . '.</error>');
            return 1;
        }

        if (! $this->sessionExists($sessionName->toString())) {
            return $this->sessionNotFoundError($output, (string) $sessionName);
        }

        $directory = rtrim($input->getOption('path') ?? config("tailor.export.{$format}_path"), '/');
        $file = $directory . '/' . SessionExporter::fileName($sessionName->toString(), $format);

        if (file_exists($file) && ! $input->getOption('force')) {
            $output->writeln("<error>{$file} already exists. Use --force to overwrite it.</error>");
            return 1;
        }

        try {
            $content = $this->exporter->export(
                $this->getSessionManager()->load($sessionName),
                $format,
                config('tailor.export.command_namespace')
            );

            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new Exception("Could not create {$directory}");
            }

            if (file_put_contents($file, $content) === false) {
                throw new Exception("Could not write {$file}");
            }
        } catch (Exception $e) {
            return $this->operationFailedError($output, 'export', $e->getMessage());
        }

        $output->writeln("<info>✓ Exported '{$sessionName}' to {$file}</info>");

        foreach ($this->exporter->getUnresolved() as $class) {
            $output->writeln("<comment>Could not resolve class '{$class}'; the command will likely fail where it is used.</comment>");
        }

        $output->writeln($format === SessionExporter::COMMAND
            ? "<comment>Run it with: php artisan tailor:{$sessionName}</comment>"
            : "<comment>Run it with: php artisan tinker {$file}</comment>");

        return 0;
    }
}
