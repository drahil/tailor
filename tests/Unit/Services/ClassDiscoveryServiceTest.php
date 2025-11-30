<?php

declare(strict_types=1);

use drahil\Tailor\Services\ClassDiscoveryService;

test('discovers classes in current project', function () {
    $service = new ClassDiscoveryService();

    $classes = $service->discoverClasses();

    expect($classes)->toBeArray();
});

test('discovered classes have fully qualified names as keys', function () {
    $service = new ClassDiscoveryService();

    $classes = $service->discoverClasses();

    if (! empty($classes)) {
        $firstKey = array_key_first($classes);
        expect($firstKey)->toContain('\\');
    }
});

test('discovered classes have file paths as values', function () {
    $service = new ClassDiscoveryService();

    $classes = $service->discoverClasses();

    foreach ($classes as $path) {
        expect($path)->toBeString();
        break;
    }
});
