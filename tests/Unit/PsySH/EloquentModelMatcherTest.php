<?php

declare(strict_types=1);

namespace drahil\Tailor\Tests\Unit\PsySH;

use drahil\Tailor\PsySH\Matchers\EloquentModelMatcher;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
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
