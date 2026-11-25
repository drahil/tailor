<?php

declare(strict_types=1);

use drahil\Tailor\Providers\TailorServiceProvider;
use drahil\Tailor\Services\CommandFilterService;
use drahil\Tailor\Services\HistoryCaptureService;
use drahil\Tailor\Services\SessionManager;
use drahil\Tailor\Services\SessionTracker;
use Illuminate\Container\Container;
use Illuminate\Support\Arr;

function providerApp(array $config = []): Container
{
    $app = new class extends Container {
        public function runningInConsole(): bool
        {
            return false;
        }
    };

    $app->instance('config', new class ($config) {
        public function __construct(private array $items) {}

        public function get(string $key, mixed $default = null): mixed
        {
            return Arr::get($this->items, $key, $default);
        }

        public function set(string $key, mixed $value): void
        {
            Arr::set($this->items, $key, $value);
        }
    });

    return $app;
}

test('merges the package config for unpublished installs', function () {
    $app = providerApp();

    (new TailorServiceProvider($app))->register();

    expect($app->make('config')->get('tailor.production.safe_tag'))->toBe('safe-for-prod')
        ->and($app->make('config')->get('tailor.autocomplete.enabled'))->toBeTrue();
});

test('keeps published config values over the package defaults', function () {
    $app = providerApp(['tailor' => ['production' => ['safe_tag' => 'reviewed']]]);

    (new TailorServiceProvider($app))->register();

    expect($app->make('config')->get('tailor.production.safe_tag'))->toBe('reviewed')
        ->and($app->make('config')->get('tailor.session.auto_save'))->toBeFalse();
});

test('registers the services as singletons', function () {
    $app = providerApp();

    (new TailorServiceProvider($app))->register();

    foreach ([SessionManager::class, SessionTracker::class, HistoryCaptureService::class, CommandFilterService::class] as $service) {
        expect($app->isShared($service))->toBeTrue();
    }
});
