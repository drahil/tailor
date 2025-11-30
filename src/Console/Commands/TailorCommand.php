<?php

declare(strict_types=1);

namespace drahil\Tailor\Console\Commands;

use drahil\Tailor\Services\ClassDiscoveryCache;
use drahil\Tailor\Services\ClassDiscoveryService;
use drahil\Tailor\Services\ClassImportManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psy\Shell;
use Psy\Configuration;

class TailorCommand extends Command
{
    public function __construct()
    {
        parent::__construct('tailor');
    }

    public function configure(): void
    {
        $this->setDescription('Interactive REPL with auto-imported classes');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $includeFile = $this->prepareAutoImports($output);

        $config = new Configuration([
            'startupMessage' => $this->getStartupMessage(),
        ]);

        $this->addLaravelCasters($config);

        $shell = new Shell($config);

        if ($includeFile !== null) {
            $shell->setIncludes([$includeFile]);
        }

        $shell->run();

        if ($includeFile !== null && file_exists($includeFile)) {
            @unlink($includeFile);
        }

        return Command::SUCCESS;
    }

    /**
     * Prepare auto-imports by discovering classes and generating use statements.
     */
    private function prepareAutoImports(OutputInterface $output): ?string
    {
        try {
            $cache = new ClassDiscoveryCache();
            $discoveryService = new ClassDiscoveryService();
            $importManager = new ClassImportManager();

            $classes = $cache->getOrDiscover($discoveryService);

            if (empty($classes)) {
                $output->writeln('<comment>No App classes found to auto-import.</comment>');
                return null;
            }

            $output->writeln('<info>Auto-importing ' . count($classes) . ' classes...</info>');

            $useStatements = $importManager->generateUseStatements($classes);

            return $this->writeIncludeFile($useStatements);
        } catch (\Exception $e) {
            $output->writeln('<error>Auto-import failed: ' . $e->getMessage() . '</error>');
            $output->writeln('<error>at ' . $e->getFile() . ':' . $e->getLine() . '</error>');
            return null;
        }
    }

    /**
     * Write use statements to a temporary include file.
     *
     * @param array<string> $useStatements
     */
    private function writeIncludeFile(array $useStatements): ?string
    {
        $content = "<?php\n\n" . implode("\n", $useStatements) . "\n";

        $tempFile = sys_get_temp_dir() . '/tailor_imports_' . uniqid() . '.php';

        if (@file_put_contents($tempFile, $content) === false) {
            return null;
        }

        return $tempFile;
    }

    /**
     * Get the startup message.
     */
    private function getStartupMessage(): string
    {
        return <<<'MESSAGE'
Welcome to Tailor!

Classes from your App namespace are auto-imported.
Type 'User::' and press Tab for autocomplete.
MESSAGE;
    }

    /**
     * Add Laravel-specific casters for better output formatting.
     */
    private function addLaravelCasters(Configuration $config): void
    {
        if (! class_exists('Laravel\Tinker\TinkerCaster')) {
            return;
        }

        $casters = [
            'Illuminate\Support\Collection' => 'Laravel\Tinker\TinkerCaster::castCollection',
            'Illuminate\Support\HtmlString' => 'Laravel\Tinker\TinkerCaster::castHtmlString',
            'Illuminate\Support\Stringable' => 'Laravel\Tinker\TinkerCaster::castStringable',
        ];

        if (class_exists('Illuminate\Database\Eloquent\Model')) {
            $casters['Illuminate\Database\Eloquent\Model'] = 'Laravel\Tinker\TinkerCaster::castModel';
        }

        if (class_exists('Illuminate\Process\ProcessResult')) {
            $casters['Illuminate\Process\ProcessResult'] = 'Laravel\Tinker\TinkerCaster::castProcessResult';
        }

        if (class_exists('Illuminate\Foundation\Application')) {
            $casters['Illuminate\Foundation\Application'] = 'Laravel\Tinker\TinkerCaster::castApplication';
        }

        $config->getPresenter()->addCasters($casters);
    }
}
