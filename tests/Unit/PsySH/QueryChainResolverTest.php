<?php

declare(strict_types=1);

namespace drahil\Tailor\Tests\Unit\PsySH;

use drahil\Tailor\PsySH\Matchers\QueryChainResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Mockery;

class ResolverTestPost extends Model
{
    public function scopePopular(Builder $query): void {}
}

function resolveChain(string $code, array $variables = []): ?array
{
    return (new QueryChainResolver(fn (string $name) => $variables[$name] ?? null))->resolve($code);
}

$post = ResolverTestPost::class;

test('a scope or a query method on a model gives a builder', function (string $code) {
    expect(resolveChain($code))->toBe([QueryChainResolver::BUILDER, ResolverTestPost::class]);
})->with([
    'scope' => "{$post}::popular(4000)",
    'query method' => "{$post}::where('a', 1)",
    'query()' => "{$post}::query()",
    'chain' => "{$post}::popular()->orderBy('id')->limit(5)",
]);

test('methods that return a model give the model', function (string $method) {
    expect(resolveChain(ResolverTestPost::class . "::where('a', 1)->{$method}"))->toBe([QueryChainResolver::MODEL, ResolverTestPost::class]);
})->with(['first()', 'firstOrFail()', 'find(1)', 'create([])']);

test('methods that return a collection give the collection class', function () {
    $post = ResolverTestPost::class;

    expect(resolveChain("{$post}::all()"))->toBe([QueryChainResolver::COLLECTION, EloquentCollection::class])
        ->and(resolveChain("{$post}::popular()->get()"))->toBe([QueryChainResolver::COLLECTION, EloquentCollection::class])
        ->and(resolveChain("{$post}::popular()->pluck('id')"))->toBe([QueryChainResolver::COLLECTION, Collection::class]);
});

test('variables resolve from their value', function () {
    $builder = (new Builder(Mockery::mock(QueryBuilder::class)->shouldIgnoreMissing()))->setModel(new ResolverTestPost());

    expect(resolveChain('$query', ['query' => $builder]))->toBe([QueryChainResolver::BUILDER, ResolverTestPost::class])
        ->and(resolveChain('$query->popular()', ['query' => $builder]))->toBe([QueryChainResolver::BUILDER, ResolverTestPost::class])
        ->and(resolveChain('$post', ['post' => new ResolverTestPost()]))->toBe([QueryChainResolver::MODEL, ResolverTestPost::class])
        ->and(resolveChain('$items', ['items' => collect()]))->toBe([QueryChainResolver::COLLECTION, Collection::class]);
});

test('unknown chains resolve to nothing', function (string $code) {
    expect(resolveChain($code))->toBeNull();
})->with([
    'unknown method' => "{$post}::popular()->somethingElse()",
    'method returning a scalar' => "{$post}::popular()->count()->foo()",
    'chain on a model' => "{$post}::first()->fresh()",
    'not a model' => 'ArrayObject::where()',
    'missing class' => 'NoSuchModel::where()',
    'unknown variable' => '$missing->where()',
    'not PHP' => "{$post}::where(",
]);

afterEach(function () {
    Mockery::close();
});
