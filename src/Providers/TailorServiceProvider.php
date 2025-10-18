<?php

declare(strict_types=1);

namespace drahil\Tailor\Providers;

use drahil\Tailor\Console\Commands\TailorCommand;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Tailor package.
 *
 * Registers commands and provides configuration publishing.
 * Services marked with #[Singleton] are automatically discovered
 * by Laravel's container and don't need explicit registration.
 */
class TailorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Publish configuration file
        // $this->publishes([
        //     __DIR__ . '/../../config/tailor.php' => config_path('tailor.php'),
        // ], 'tailor-config');
    }

    public function register(): void
    {
        // Merge package configuration
        // $this->mergeConfigFrom(__DIR__ . '/../../config/tailor.php', 'tailor');

        // Register console commands
        $this->commands([
            TailorCommand::class,
        ]);

        // Note: Services with #[Singleton] attribute are auto-discovered:
        // - SessionManager
        // - SessionTracker
    }
}