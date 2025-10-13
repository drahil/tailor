<?php

declare(strict_types=1);

namespace drahil\Tailor\Providers;

use Illuminate\Support\ServiceProvider;

class TailorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/tailor.php' => config_path('tailor.php'),
        ], 'config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/tailor.php', 'tailor');
    }
}