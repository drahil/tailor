<?php

declare(strict_types=1);

namespace drahil\Tailor\Providers;

use drahil\Tailor\Console\Commands\TailorCommand;
use drahil\Tailor\Services\CommandFilterService;
use drahil\Tailor\Services\HistoryCaptureService;
use drahil\Tailor\Services\SessionManager;
use drahil\Tailor\Services\SessionTracker;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Tailor package.
 */
class TailorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
         $this->publishes([
             __DIR__ . '/../../config/tailor.php' => config_path('tailor.php'),
         ], 'tailor-config');
    }

    public function register(): void
    {
        $this->app->singleton(SessionManager::class);
        $this->app->singleton(SessionTracker::class);
        $this->app->singleton(HistoryCaptureService::class);
        $this->app->singleton(CommandFilterService::class);

        $this->commands([
            TailorCommand::class,
        ]);
    }
}