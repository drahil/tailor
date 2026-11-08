<?php

declare(strict_types=1);

use drahil\Tailor\Services\ClassDiscoveryService;

test('discovers classes from the project autoload map', function () {
    $service = new ClassDiscoveryService(['drahil\\Tailor\\']);

    $classes = $service->discoverClasses();

    expect($classes)->toHaveKey(ClassDiscoveryService::class)
        ->and($classes[ClassDiscoveryService::class])->toBe(realpath(base_path('src/Services/ClassDiscoveryService.php')));
});

test('discovers nothing for namespaces that are not scanned', function () {
    $service = new ClassDiscoveryService(['App\\']);

    expect($service->discoverClasses())->toBe([]);
});

function extractClassNames(string $contents): array
{
    $method = new ReflectionMethod(ClassDiscoveryService::class, 'extractClassNames');

    return $method->invoke(new ClassDiscoveryService(['App\\']), $contents);
}

test('extracts declared classes, interfaces, traits and enums', function () {
    $code = <<<'PHP'
    <?php
    namespace App;
    final class Foo {}
    interface Bar {}
    trait Baz {}
    enum Qux: string {}
    PHP;

    expect(extractClassNames($code))->toBe(['Foo', 'Bar', 'Baz', 'Qux']);
});

test('ignores ::class references', function () {
    $code = <<<'PHP'
    <?php
    namespace App\Providers;
    class AppServiceProvider
    {
        public function boot(): void
        {
            $this->app->bind(Foo::class, Bar::class);
            Gate::policy(Post::class, PostPolicy::class);
            $name = static :: class;
            baz();
        }
    }
    PHP;

    expect(extractClassNames($code))->toBe(['AppServiceProvider']);
});

test('ignores anonymous classes', function () {
    $code = <<<'PHP'
    <?php
    return new class ($arg) extends Base {
        public function handle(): void { helper(); }
    };
    $other = new class { };
    PHP;

    expect(extractClassNames($code))->toBe([]);
});
