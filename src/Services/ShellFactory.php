<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

use Psy\Configuration;
use Psy\Shell;

class ShellFactory
{
    /**
     * Create a PsySH configuration with the given startup message.
     */
    public function createConfiguration(?string $startupMessage = null): Configuration
    {
        $config = new Configuration([
            'startupMessage' => $startupMessage ?? $this->getDefaultStartupMessage(),
        ]);

        $this->addLaravelCasters($config);

        return $config;
    }

    /**
     * Create a configured PsySH shell instance.
     */
    public function create(Configuration $config): Shell
    {
        return new Shell($config);
    }

    /**
     * Get the default startup message.
     */
    private function getDefaultStartupMessage(): string
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
