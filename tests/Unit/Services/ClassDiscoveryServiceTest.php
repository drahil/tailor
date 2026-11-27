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

test('fingerprint is stable and depends on the scanned namespaces', function () {
    $tailor = new ClassDiscoveryService(['drahil\\Tailor\\']);

    expect($tailor->fingerprint())->toBe($tailor->fingerprint())
        ->and($tailor->fingerprint())->not->toBe((new ClassDiscoveryService(['App\\']))->fingerprint());
});

test('skips only folders named tests', function () {
    $directory = sys_get_temp_dir() . '/tailor-discovery-' . uniqid();
    $files = [
        'Http/Controllers/ContestsController.php' => '<?php namespace App\Http\Controllers; class ContestsController {}',
        'tests/FooTest.php' => '<?php namespace App\tests; class FooTest {}',
        'Feature/tests/BarTest.php' => '<?php namespace App\Feature\tests; class BarTest {}',
    ];

    foreach ($files as $path => $contents) {
        @mkdir(dirname("{$directory}/{$path}"), 0777, true);
        file_put_contents("{$directory}/{$path}", $contents);
    }

    $method = new ReflectionMethod(ClassDiscoveryService::class, 'scanDirectory');
    $classes = $method->invoke(new ClassDiscoveryService(['App\\']), 'App\\', $directory);

    expect(array_keys($classes))->toBe(['App\\Http\\Controllers\\ContestsController']);

    (new Illuminate\Filesystem\Filesystem())->deleteDirectory($directory);
});
