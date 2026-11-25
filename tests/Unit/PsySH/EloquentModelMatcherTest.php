<?php

declare(strict_types=1);

namespace drahil\Tailor\Tests\Unit\PsySH;

use drahil\Tailor\PsySH\Matchers\EloquentModelMatcher;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Mockery;
use Psy\Context;
use Psy\TabCompletion\Matcher\AbstractMatcher;

class MatcherTestPost extends Model
{
    #[Scope]
    protected function published(Builder $query): void {}

    public function scopePopular(Builder $query): void {}

    protected function excerpt(): Attribute
    {
        return Attribute::get(fn () => '');
    }

    public function getReadingTimeAttribute(): int
    {
        return 1;
    }
}

class MatcherTestComment extends Model
{
    use SoftDeletes;
}

function builderFor(Model $model): Builder
{
    return (new Builder(Mockery::mock(QueryBuilder::class)->shouldIgnoreMissing()))->setModel($model);
}

function completeLine(string $line, array $variables = []): array
{
    $context = new Context();
    $context->setAll($variables);

    $matcher = new EloquentModelMatcher();
    $matcher->setContext($context);

    $tokens = array_values(array_filter(
        array_slice(token_get_all('<?php ' . $line), 1),
        fn ($token) => ! AbstractMatcher::tokenIs($token, AbstractMatcher::T_WHITESPACE)
    ));

    return $matcher->hasMatched($tokens) ? array_values($matcher->getMatches($tokens)) : [];
}

test('suggests attribute scopes on static calls', function () {
    expect(completeLine(MatcherTestPost::class . '::pub'))->toBe(['published']);
});

test('suggests classic scopes on static calls', function () {
    expect(completeLine(MatcherTestPost::class . '::pop'))->toBe(['popular']);
});

test('suggests scopes and both accessor styles on instances', function () {
    $matches = completeLine('$post->', ['post' => new MatcherTestPost()]);

    expect($matches)->toContain('published', 'popular', 'excerpt', 'reading_time');
});

test('still suggests query builder methods on static calls', function () {
    expect(completeLine(MatcherTestPost::class . '::firstOr'))->toBe(['firstOrNew', 'firstOrCreate', 'firstOrFail']);
});

test('suggests forwarded query methods after a scope chain', function () {
    expect(completeLine(MatcherTestPost::class . '::popular(4000)->cou'))->toContain('count');
});

test('suggests scopes after a where chain', function () {
    expect(completeLine(MatcherTestPost::class . "::where('a', 1)->pub"))->toBe(['published']);
});

test('suggests model accessors after a call that returns a model', function () {
    expect(completeLine(MatcherTestPost::class . '::first()->exc'))->toBe(['excerpt', 'except']);
});

test('suggests collection methods after a call that returns a collection', function () {
    expect(completeLine(MatcherTestPost::class . '::where("a", 1)->get()->keyB'))->toBe(['keyBy']);
});

test('suggests scopes on a variable holding a query builder', function () {
    $matches = completeLine('$query->pop', ['query' => builderFor(new MatcherTestPost())]);

    expect($matches)->toBe(['popular']);
});

test('only completes the expression right before the arrow', function () {
    expect(completeLine('dd(' . MatcherTestPost::class . "::where('a', 1)->pub"))->toBe(['published']);
});

test('suggests soft delete methods only for models that use SoftDeletes', function () {
    expect(completeLine(MatcherTestComment::class . '::where("a", 1)->withT'))->toBe(['withTrashed'])
        ->and(completeLine(MatcherTestPost::class . '::where("a", 1)->withT'))->toBe([]);
});

test('suggests nothing after an unknown call in the chain', function () {
    expect(completeLine(MatcherTestPost::class . '::where("a", 1)->unknownThing()->cou'))->toBe([]);
});
