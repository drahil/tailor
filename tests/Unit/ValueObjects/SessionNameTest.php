<?php

declare(strict_types=1);

use drahil\Tailor\Support\Validation\ValidationException;
use drahil\Tailor\Support\ValueObjects\SessionName;

test('lowercases session names', function () {
    expect(SessionName::from('My-Work')->toString())->toBe('my-work')
        ->and((new SessionName('API_Tests'))->toString())->toBe('api_tests');
});

test('rejects names with invalid characters', function () {
    SessionName::from('../etc/passwd');
})->throws(ValidationException::class);
