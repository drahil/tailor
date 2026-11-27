<?php

declare(strict_types=1);

use drahil\Tailor\Services\ClassDiscoveryCache;
use drahil\Tailor\Services\ClassDiscoveryService;

beforeEach(function () {
    $this->cache = new ClassDiscoveryCache();
    $this->cache->clear();
});

afterEach(function () {
    $this->cache->clear();
    Mockery::close();
});

test('reuses the cached classes while the fingerprint is unchanged', function () {
    $service = Mockery::mock(ClassDiscoveryService::class);
    $service->shouldReceive('fingerprint')->andReturn('abc');
    $service->shouldReceive('discoverClasses')->once()->andReturn(['App\\Models\\User' => '/app/Models/User.php']);

    $this->cache->getOrDiscover($service);

    expect($this->cache->getOrDiscover($service))->toBe(['App\\Models\\User' => '/app/Models/User.php']);
});

test('rediscovers when the fingerprint changes', function () {
    $service = Mockery::mock(ClassDiscoveryService::class);
    $service->shouldReceive('fingerprint')->andReturn('abc', 'def');
    $service->shouldReceive('discoverClasses')->twice()->andReturn(
        ['App\\Models\\User' => '/app/Models/User.php'],
        ['App\\Models\\User' => '/app/Models/User.php', 'App\\Models\\Post' => '/app/Models/Post.php'],
    );

    $this->cache->getOrDiscover($service);

    expect($this->cache->getOrDiscover($service))->toHaveKey('App\\Models\\Post');
});
