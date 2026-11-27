<?php

declare(strict_types=1);

use drahil\Tailor\Support\Validation\AttributeValidator;
use drahil\Tailor\Support\Validation\Attributes\MaxLength;
use drahil\Tailor\Support\Validation\Attributes\NotEmpty;
use drahil\Tailor\Support\Validation\Attributes\Pattern;
use drahil\Tailor\Support\Validation\ValidationException;
use drahil\Tailor\Support\Validation\ValidationResult;

final class ValidatedThing
{
    public function __construct(
        #[NotEmpty(message: 'Name is required')]
        #[Pattern('/^[a-z]+$/', message: 'Lowercase letters only')]
        public mixed $name,
        #[MaxLength(5, message: 'Too long')]
        public ?string $note = null,
    ) {}
}

test('NotEmpty rejects null, blank strings and empty arrays', function (mixed $value, bool $valid) {
    expect((new NotEmpty())->validate($value))->toBe($valid);
})->with([
    [null, false],
    ['  ', false],
    [[], false],
    ['a', true],
    [0, true],
    [['a'], true],
]);

test('Pattern matches strings and leaves null to NotEmpty', function () {
    $pattern = new Pattern('/^[a-z]+$/');

    expect($pattern->validate('abc'))->toBeTrue()
        ->and($pattern->validate('Abc'))->toBeFalse()
        ->and($pattern->validate(null))->toBeTrue();
});

test('MaxLength counts characters, not bytes, and reports both lengths', function () {
    $maxLength = new MaxLength(3, message: 'Too long');

    expect($maxLength->validate('äöü'))->toBeTrue()
        ->and($maxLength->validate('abcd'))->toBeFalse()
        ->and($maxLength->validate(null))->toBeTrue()
        ->and($maxLength->getFormattedMessage('abcd'))->toBe('Too long (maximum: 3, actual: 4)');
});

test('AttributeValidator collects the errors of every property', function () {
    $result = AttributeValidator::validate(new ValidatedThing('', 'far too long'));

    expect($result->errors)->toBe([
        'name: Name is required',
        'name: Lowercase letters only',
        'note: Too long (maximum: 5, actual: 12)',
    ]);
});

test('AttributeValidator passes valid objects', function () {
    expect(AttributeValidator::validate(new ValidatedThing('abc', 'ok'))->hasErrors())->toBeFalse();
});

test('validateOrFail throws with the validation result', function () {
    try {
        AttributeValidator::validateOrFail(new ValidatedThing('ABC'));
        $this->fail('Expected a ValidationException');
    } catch (ValidationException $e) {
        expect($e->result->firstError())->toBe('name: Lowercase letters only');
    }
});

test('ValidationResult reports its errors', function () {
    $result = new ValidationResult(['a', 'b']);

    expect($result->hasErrors())->toBeTrue()
        ->and($result->firstError())->toBe('a')
        ->and($result->errorsAsString())->toBe('a, b')
        ->and($result->errorsAsString(' | '))->toBe('a | b')
        ->and((new ValidationResult())->firstError())->toBeNull();
});
