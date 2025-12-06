<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

use Psy\Configuration;

class ShellFactory
{
    /**
     * Add Laravel-specific casters for better output formatting.
     */
    public function addLaravelCasters(Configuration $config): void
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
