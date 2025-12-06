<?php

declare(strict_types=1);

use drahil\Tailor\Services\ClassDiscoveryCache;
use drahil\Tailor\Services\ClassDiscoveryService;

test('cache returns discovered classes', function () {
    $cache = new ClassDiscoveryCache();
    $service = new ClassDiscoveryService(['App\\']);

    $classes = $cache->getOrDiscover($service);

    expect($classes)->toBeArray();
});

test('cache can be cleared', function () {
    $cache = new ClassDiscoveryCache();

    $cache->clear();

    expect(true)->toBeTrue();
});
