<?php

declare(strict_types=1);

use drahil\Tailor\Services\ClassDiscoveryService;

test('discovers classes in current project', function () {
    $service = new ClassDiscoveryService(['App\\']);

    $classes = $service->discoverClasses();

    expect($classes)->toBeArray();
});

test('discovered classes have fully qualified names as keys', function () {
    $service = new ClassDiscoveryService(['App\\']);

    $classes = $service->discoverClasses();

    if (! empty($classes)) {
        $firstKey = array_key_first($classes);
        expect($firstKey)->toContain('\\');
    }
});

test('discovered classes have file paths as values', function () {
    $service = new ClassDiscoveryService(['App\\']);

    $classes = $service->discoverClasses();

    foreach ($classes as $path) {
        expect($path)->toBeString();
        break;
    }
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
