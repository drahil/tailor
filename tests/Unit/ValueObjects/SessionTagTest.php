<?php

declare(strict_types=1);

use drahil\Tailor\Support\Validation\ValidationException;
use drahil\Tailor\Support\ValueObjects\SessionTag;

test('creates valid tag with alphanumeric characters', function () {
    $tag = SessionTag::from('api-testing_123');

    expect($tag->toString())->toBe('api-testing_123');
});

test('normalizes tag to lowercase', function () {
    $tag = SessionTag::from('API-Testing');

    expect($tag->toString())->toBe('api-testing');
});

test('trims whitespace from tag', function () {
    $tag = SessionTag::from('  debug  ');

    expect($tag->toString())->toBe('debug');
});

test('throws exception for empty tag', function () {
    SessionTag::from('');
})->throws(ValidationException::class);

test('throws exception for tag with only whitespace', function () {
    SessionTag::from('   ');
})->throws(ValidationException::class);

test('throws exception for tag with spaces', function () {
    SessionTag::from('my tag');
})->throws(ValidationException::class);

test('throws exception for tag with invalid characters', function () {
    SessionTag::from('tag@name');
})->throws(ValidationException::class);

test('throws exception for tag with special characters', function () {
    SessionTag::from('tag!name');
})->throws(ValidationException::class);

test('throws exception for tag exceeding max length', function () {
    $longTag = str_repeat('a', 51);
    SessionTag::from($longTag);
})->throws(ValidationException::class);

test('accepts tag at max length boundary', function () {
    $maxTag = str_repeat('a', 50);
    $tag = SessionTag::from($maxTag);

    expect($tag->toString())->toBe($maxTag);
});

test('allows hyphens in tag', function () {
    $tag = SessionTag::from('api-debug');

    expect($tag->toString())->toBe('api-debug');
});

test('allows underscores in tag', function () {
    $tag = SessionTag::from('api_debug');

    expect($tag->toString())->toBe('api_debug');
});

test('allows numbers in tag', function () {
    $tag = SessionTag::from('v2-api');

    expect($tag->toString())->toBe('v2-api');
});

test('converts to string via __toString', function () {
    $tag = SessionTag::from('testing');

    expect((string) $tag)->toBe('testing');
});

test('creates multiple tags from array', function () {
    $tags = SessionTag::fromArray(['api', 'DEBUG', 'testing']);

    expect($tags)->toHaveCount(3)
        ->and($tags[0]->toString())->toBe('api')
        ->and($tags[1]->toString())->toBe('debug')
        ->and($tags[2]->toString())->toBe('testing');
});

test('checks equality between tags', function () {
    $tag1 = SessionTag::from('api');
    $tag2 = SessionTag::from('API');
    $tag3 = SessionTag::from('debug');

    expect($tag1->equals($tag2))->toBeTrue()
        ->and($tag1->equals($tag3))->toBeFalse();
});
