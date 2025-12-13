<?php

declare(strict_types=1);

use drahil\Tailor\Support\Validation\ValidationException;
use drahil\Tailor\Support\ValueObjects\SessionTag;
use drahil\Tailor\Support\ValueObjects\SessionTags;

test('creates empty collection', function () {
    $tags = new SessionTags();

    expect($tags->isEmpty())->toBeTrue()
        ->and($tags->count())->toBe(0)
        ->and($tags->toArray())->toBe([]);
});

test('creates collection from strings', function () {
    $tags = new SessionTags(['api', 'debug', 'testing']);

    expect($tags->count())->toBe(3)
        ->and($tags->toArray())->toBe(['api', 'debug', 'testing']);
});

test('creates collection from SessionTag objects', function () {
    $tag1 = SessionTag::from('api');
    $tag2 = SessionTag::from('debug');

    $tags = new SessionTags([$tag1, $tag2]);

    expect($tags->count())->toBe(2)
        ->and($tags->toArray())->toBe(['api', 'debug']);
});

test('creates collection from mixed strings and SessionTag objects', function () {
    $tag1 = SessionTag::from('api');

    $tags = new SessionTags([$tag1, 'debug', 'testing']);

    expect($tags->count())->toBe(3)
        ->and($tags->toArray())->toBe(['api', 'debug', 'testing']);
});

test('removes duplicate tags', function () {
    $tags = new SessionTags(['api', 'debug', 'API', 'testing', 'Debug']);

    expect($tags->count())->toBe(3)
        ->and($tags->toArray())->toBe(['api', 'debug', 'testing']);
});

test('throws exception when exceeding max tag count', function () {
    $manyTags = array_map(fn($i) => "tag{$i}", range(1, 11));

    new SessionTags($manyTags);
})->throws(ValidationException::class);

test('accepts tags at max count boundary', function () {
    $maxTags = array_map(fn($i) => "tag{$i}", range(1, 10));

    $tags = new SessionTags($maxTags);

    expect($tags->count())->toBe(10);
});

test('checks if collection contains a tag by string', function () {
    $tags = new SessionTags(['api', 'debug']);

    expect($tags->contains('api'))->toBeTrue()
        ->and($tags->contains('API'))->toBeTrue()
        ->and($tags->contains('testing'))->toBeFalse();
});

test('checks if collection contains a tag by SessionTag object', function () {
    $tags = new SessionTags(['api', 'debug']);
    $apiTag = SessionTag::from('API');
    $testTag = SessionTag::from('testing');

    expect($tags->contains($apiTag))->toBeTrue()
        ->and($tags->contains($testTag))->toBeFalse();
});

test('adds tags to collection', function () {
    $tags = new SessionTags(['api']);
    $newTags = $tags->add(['debug', 'testing']);

    expect($newTags->count())->toBe(3)
        ->and($newTags->toArray())->toBe(['api', 'debug', 'testing']);
});

test('adding tags removes duplicates', function () {
    $tags = new SessionTags(['api', 'debug']);
    $newTags = $tags->add(['API', 'testing']);

    expect($newTags->count())->toBe(3)
        ->and($newTags->toArray())->toBe(['api', 'debug', 'testing']);
});

test('removes tags from collection', function () {
    $tags = new SessionTags(['api', 'debug', 'testing']);
    $newTags = $tags->remove(['debug']);

    expect($newTags->count())->toBe(2)
        ->and($newTags->toArray())->toBe(['api', 'testing']);
});

test('removes tags case-insensitively', function () {
    $tags = new SessionTags(['api', 'debug', 'testing']);
    $newTags = $tags->remove(['DEBUG', 'API']);

    expect($newTags->count())->toBe(1)
        ->and($newTags->toArray())->toBe(['testing']);
});

test('removing non-existent tag does not affect collection', function () {
    $tags = new SessionTags(['api', 'debug']);
    $newTags = $tags->remove(['testing']);

    expect($newTags->count())->toBe(2)
        ->and($newTags->toArray())->toBe(['api', 'debug']);
});

test('checks if collection has all specified tags', function () {
    $tags = new SessionTags(['api', 'debug', 'testing']);

    expect($tags->hasAll(['api', 'debug']))->toBeTrue()
        ->and($tags->hasAll(['api', 'missing']))->toBeFalse()
        ->and($tags->hasAll([]))->toBeTrue();
});

test('hasAll is case-insensitive', function () {
    $tags = new SessionTags(['api', 'debug']);

    expect($tags->hasAll(['API', 'DEBUG']))->toBeTrue();
});

test('creates empty collection via static factory', function () {
    $tags = SessionTags::empty();

    expect($tags->isEmpty())->toBeTrue();
});

test('creates collection from array via static factory', function () {
    $tags = SessionTags::fromArray(['api', 'debug']);

    expect($tags->count())->toBe(2)
        ->and($tags->toArray())->toBe(['api', 'debug']);
});

test('collection is immutable when adding tags', function () {
    $original = new SessionTags(['api']);
    $modified = $original->add(['debug']);

    expect($original->count())->toBe(1)
        ->and($modified->count())->toBe(2);
});

test('collection is immutable when removing tags', function () {
    $original = new SessionTags(['api', 'debug']);
    $modified = $original->remove(['debug']);

    expect($original->count())->toBe(2)
        ->and($modified->count())->toBe(1);
});

test('validates tags when creating collection', function () {
    new SessionTags(['invalid tag']);
})->throws(ValidationException::class);

test('validates tags when adding to collection', function () {
    $tags = new SessionTags(['api']);

    $tags->add(['invalid tag']);
})->throws(ValidationException::class);
