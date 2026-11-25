<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH\Matchers;

use Closure;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use PhpParser\Error;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use PhpParser\ParserFactory;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Works out what an Eloquent query chain such as `Post::popular(4000)->where(...)` returns.
 */
class QueryChainResolver
{
    public const BUILDER = 'builder';
    public const MODEL = 'model';
    public const COLLECTION = 'collection';

    private const MODEL_METHODS = [
        'create', 'createorfirst', 'find', 'findorfail', 'findornew', 'first', 'firstorcreate',
        'firstorfail', 'firstornew', 'firstwhere', 'forcecreate', 'make', 'sole', 'updateorcreate',
    ];

    private const COLLECTION_METHODS = [
        'all' => EloquentCollection::class,
        'findmany' => EloquentCollection::class,
        'get' => EloquentCollection::class,
        'pluck' => Collection::class,
    ];

    private const QUERY_METHODS = ['newmodelquery', 'newquery', 'query'];

    /**
     * @param Closure(string): mixed $variable Looks up a shell variable by name
     */
    public function __construct(private readonly Closure $variable) {}

    /**
     * @return array{0: string, 1: class-string}|null Kind and class, or null when the result is unknown
     */
    public function resolve(string $code): ?array
    {
        try {
            $statements = (new ParserFactory())->createForNewestSupportedVersion()->parse("<?php {$code};");
        } catch (Error) {
            return null;
        }

        if (count($statements ?? []) !== 1 || ! $statements[0] instanceof Expression) {
            return null;
        }

        return $this->resolveExpression($statements[0]->expr);
    }

    /**
     * @return array{0: string, 1: class-string}|null
     */
    private function resolveExpression(Expr $expr): ?array
    {
        if ($expr instanceof Expr\Variable && is_string($expr->name)) {
            return $this->resolveValue(($this->variable)($expr->name));
        }

        if (! ($expr instanceof Expr\StaticCall || $expr instanceof Expr\MethodCall) || ! $expr->name instanceof Identifier) {
            return null;
        }

        $method = $expr->name->toLowerString();

        if ($expr instanceof Expr\StaticCall) {
            if (! $expr->class instanceof Name) {
                return null;
            }

            $model = $expr->class->toString();

            if (! class_exists($model) || ! is_subclass_of($model, Model::class)) {
                return null;
            }

            return in_array($method, self::QUERY_METHODS, true)
                ? [self::BUILDER, $model]
                : $this->afterBuilderCall($model, $method);
        }

        $base = $this->resolveExpression($expr->var);

        return $base !== null && $base[0] === self::BUILDER
            ? $this->afterBuilderCall($base[1], $method)
            : null;
    }

    /**
     * @return array{0: string, 1: class-string}|null
     */
    private function resolveValue(mixed $value): ?array
    {
        return match (true) {
            $value instanceof EloquentBuilder => [self::BUILDER, get_class($value->getModel())],
            $value instanceof Model => [self::MODEL, get_class($value)],
            $value instanceof Collection => [self::COLLECTION, get_class($value)],
            default => null,
        };
    }

    /**
     * @param class-string $model
     * @return array{0: string, 1: class-string}|null
     */
    private function afterBuilderCall(string $model, string $method): ?array
    {
        if (in_array($method, self::MODEL_METHODS, true)) {
            return [self::MODEL, $model];
        }

        if (isset(self::COLLECTION_METHODS[$method])) {
            return [self::COLLECTION, self::COLLECTION_METHODS[$method]];
        }

        if ($this->isScope($model, $method) || $this->returnsBuilder($method)) {
            return [self::BUILDER, $model];
        }

        return null;
    }

    /**
     * @param class-string $model
     */
    private function isScope(string $model, string $method): bool
    {
        $reflection = new ReflectionClass($model);

        if ($reflection->hasMethod('scope' . $method)) {
            return true;
        }

        return $reflection->hasMethod($method)
            && $reflection->getMethod($method)->getAttributes(Scope::class) !== [];
    }

    private function returnsBuilder(string $method): bool
    {
        foreach ([EloquentBuilder::class, QueryBuilder::class] as $builder) {
            if (! method_exists($builder, $method)) {
                continue;
            }

            $reflection = new ReflectionMethod($builder, $method);

            if (! $reflection->isPublic() || $reflection->isStatic()) {
                return false;
            }

            $type = $reflection->getReturnType();

            if ($type instanceof ReflectionNamedType) {
                return in_array($type->getName(), ['static', 'self'], true)
                    || is_a($type->getName(), EloquentBuilder::class, true)
                    || is_a($type->getName(), QueryBuilder::class, true);
            }

            return preg_match('/@return\s+(\$this|static|self)\b/', (string) $reflection->getDocComment()) === 1;
        }

        return false;
    }
}
