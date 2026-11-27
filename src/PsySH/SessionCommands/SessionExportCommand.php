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
            ->setDescription('Export a saved session as an Artisan command')
            ->setHelp(<<<HELP
  Turn a saved session into an Artisan command you can run without Tailor.
  The namespace follows the folder, using Composer's PSR-4 autoload map.

  Usage:
    session:export blog-stats                                    Write app/Console/Commands/BlogStats.php
    session:export blog-stats --path=app/Console/Commands/Tailor Write to another folder
    session:export blog-stats --force                            Overwrite an existing file
  HELP
            )
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'Name of the session'
            )
            ->addOption(
                'path',
                'p',
                InputOption::VALUE_REQUIRED,
                'Folder to write the command to'
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

        if (! $this->sessionExists($sessionName->toString())) {
            return $this->sessionNotFoundError($output, (string) $sessionName);
        }

        $directory = rtrim($input->getOption('path') ?? config('tailor.export.path'), '/');
        $file = $directory . '/' . SessionExporter::fileName($sessionName->toString());
        $namespace = SessionExporter::namespaceFor($directory, $this->psr4());

        if (file_exists($file) && ! $input->getOption('force')) {
            $output->writeln("<error>{$file} already exists. Use --force to overwrite it.</error>");
            return 1;
        }

        try {
            $content = $this->exporter->export(
                $this->getSessionManager()->load($sessionName),
                $namespace ?? 'App\\Console\\Commands'
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

        $output->writeln($namespace === null
            ? "<comment>{$directory} is not in a PSR-4 autoloaded folder, so Artisan will not find the command there.</comment>"
            : "<comment>Run it with: php artisan tailor:{$sessionName}</comment>");

        return 0;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function psr4(): array
    {
        $file = base_path('vendor/composer/autoload_psr4.php');

        return file_exists($file) ? require $file : [];
    }
}
