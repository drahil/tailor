<?php

declare(strict_types=1);

namespace drahil\Tailor\Tests\Unit\PsySH;

use drahil\Tailor\PsySH\Matchers\FilteredClassAttributesMatcher;
use drahil\Tailor\PsySH\Matchers\FilteredClassMethodsMatcher;
use drahil\Tailor\PsySH\Matchers\FilteredObjectAttributesMatcher;
use drahil\Tailor\PsySH\Matchers\FilteredObjectMethodsMatcher;
use Illuminate\Database\Eloquent\Model;
use Psy\Context;
use Psy\TabCompletion\Matcher\AbstractContextAwareMatcher;
use Psy\TabCompletion\Matcher\AbstractMatcher;

class FilterTestThing
{
    public const LIMIT = 1;

    public static int $total = 0;

    public string $label = '';

    public static function makeOne(): self
    {
        return new self();
    }

    public function labelIt(): void {}
}

class FilterTestModel extends Model
{
    public const LIMIT = 1;

    public static int $total = 0;

    public string $label = '';

    public static function makeOne(): self
    {
        return new self();
    }

    public function labelIt(): void {}
}

function filteredMatches(AbstractMatcher $matcher, string $line, array $variables = []): array
{
    $context = new Context();
    $context->setAll($variables);

    if ($matcher instanceof AbstractContextAwareMatcher) {
        $matcher->setContext($context);
    }

    $tokens = array_values(array_filter(
        token_get_all('<?php ' . $line),
        fn ($token) => ! AbstractMatcher::tokenIs($token, AbstractMatcher::T_WHITESPACE)
    ));

    return $matcher->hasMatched($tokens) ? array_values($matcher->getMatches($tokens)) : [];
}

test('class method completions skip Eloquent models', function () {
    $matcher = new FilteredClassMethodsMatcher();

    expect(filteredMatches($matcher, FilterTestThing::class . '::make'))->toBe(['FilterTestThing::makeOne'])
        ->and(filteredMatches($matcher, FilterTestModel::class . '::make'))->toBe([]);
});

test('class attribute completions skip Eloquent models', function () {
    $matcher = new FilteredClassAttributesMatcher();

    expect(filteredMatches($matcher, FilterTestThing::class . '::LIM'))->toBe(['FilterTestThing::LIMIT'])
        ->and(filteredMatches($matcher, FilterTestModel::class . '::LIM'))->toBe([]);
});

test('object method completions skip Eloquent model instances', function () {
    $matcher = new FilteredObjectMethodsMatcher();

    expect(filteredMatches($matcher, '$thing->label', ['thing' => new FilterTestThing()]))->toContain('labelIt')
        ->and(filteredMatches($matcher, '$model->label', ['model' => new FilterTestModel()]))->toBe([]);
});

test('object attribute completions skip Eloquent model instances', function () {
    $matcher = new FilteredObjectAttributesMatcher();

    expect(filteredMatches($matcher, '$thing->lab', ['thing' => new FilterTestThing()]))->toContain('label')
        ->and(filteredMatches($matcher, '$model->lab', ['model' => new FilterTestModel()]))->toBe([]);
});
