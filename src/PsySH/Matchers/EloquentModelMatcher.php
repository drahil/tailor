<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH\Matchers;

use Exception;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;
use Psy\TabCompletion\Matcher\AbstractContextAwareMatcher;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Provides autocomplete for Eloquent model attributes, accessors, and query builder methods.
 */
class EloquentModelMatcher extends AbstractContextAwareMatcher
{
    /**
     * Check if this matcher can handle the given input.
     *
     * @param array<mixed> $tokens
     * @return bool
     */
    public function hasMatched(array $tokens): bool
    {
        $token = array_pop($tokens);
        $prevToken = array_pop($tokens);

        return self::tokenIs($token, self::T_OBJECT_OPERATOR)
            || self::tokenIs($prevToken, self::T_OBJECT_OPERATOR)
            || self::tokenIs($token, self::T_DOUBLE_COLON)
            || self::tokenIs($prevToken, self::T_DOUBLE_COLON);
    }

    /**
     * Get autocomplete matches for the given input.
     *
     * @param array<mixed> $tokens
     * @param array<mixed> $info
     * @return array<string>
     */
    public function getMatches(array $tokens, array $info = []): array
    {
        $input = $this->getInput($tokens);

        $firstToken = array_pop($tokens);

        if (self::tokenIs($firstToken, self::T_STRING)) {
            $operatorToken = array_pop($tokens);
        } else {
            $operatorToken = $firstToken;
        }

        if (self::tokenIs($operatorToken, self::T_OBJECT_OPERATOR)) {
            return $this->getInstanceMatches($tokens, $input);
        }

        if (self::tokenIs($operatorToken, self::T_DOUBLE_COLON)) {
            return $this->getStaticMatches($tokens, $input);
        }

        return [];
    }

    /**
     * Get matches after `->`, for model variables, query builder variables and query chains.
     *
     * @param array<mixed> $tokens
     * @param string $input
     * @return array<string>
     */
    private function getInstanceMatches(array $tokens, string $input): array
    {
        $expression = $this->trailingExpression($tokens);

        if ($expression === null) {
            return [];
        }

        $isVariable = count($expression) === 1;
        $resolver = new QueryChainResolver(function (string $name) {
            try {
                return $this->getVariable($name);
            } catch (Throwable) {
                return null;
            }
        });

        try {
            $type = $resolver->resolve(implode(' ', array_map(
                fn ($token) => is_array($token) ? $token[1] : $token,
                $expression
            )));
        } catch (Throwable) {
            return [];
        }

        if ($type === null) {
            return [];
        }

        [$kind, $class] = $type;

        if ($isVariable) {
            return match ($kind) {
                QueryChainResolver::MODEL => $this->getModelCompletions($this->getVariable(ltrim($expression[0][1], '$')), $input),
                QueryChainResolver::BUILDER => $this->getBuilderCompletions($class, $input),
                default => [],
            };
        }

        return match ($kind) {
            QueryChainResolver::MODEL => $this->getModelCompletions(new $class(), $input),
            QueryChainResolver::BUILDER => $this->getBuilderCompletions($class, $input),
            QueryChainResolver::COLLECTION => $this->filterByPrefix($this->publicMethods($class), $input),
            default => [],
        };
    }

    /**
     * The tokens of the expression right before `->`, e.g. `Post::where(...)` in `dd(Post::where(...)->`.
     *
     * @param array<mixed> $tokens
     * @return array<mixed>|null
     */
    private function trailingExpression(array $tokens): ?array
    {
        $chainTokens = [T_STRING, T_VARIABLE, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE, T_DOUBLE_COLON, T_OBJECT_OPERATOR];
        $depth = 0;
        $expression = [];

        for ($i = count($tokens) - 1; $i >= 0; $i--) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;

            if (in_array($text, [')', ']'], true)) {
                $depth++;
            } elseif (in_array($text, ['(', '['], true)) {
                if ($depth === 0) {
                    break;
                }
                $depth--;
            } elseif ($depth === 0 && ! (is_array($token) && in_array($token[0], $chainTokens, true))) {
                break;
            }

            array_unshift($expression, $token);
        }

        return $expression === [] || $depth !== 0 ? null : $expression;
    }

    /**
     * Get matches for static method access.
     *
     * @param array<mixed> $tokens
     * @param string $input
     * @return array<string>
     */
    private function getStaticMatches(array $tokens, string $input): array
    {
        $className = $this->getNamespaceAndClass($tokens);

        if (empty($className)) {
            return [];
        }

        if (! class_exists($className)) {
            return [];
        }

        $reflection = new ReflectionClass($className);

        if (! $reflection->isSubclassOf(Model::class) && $reflection->getName() !== Model::class) {
            return [];
        }

        return array_values(array_unique(array_merge(
            $this->getScopeCompletions($reflection, $input),
            $this->getQueryBuilderCompletions($input)
        )));
    }

    /**
     * Get completions for an Eloquent model instance.
     *
     * @param Model $model
     * @param string $input
     * @return array<string>
     */
    private function getModelCompletions(Model $model, string $input): array
    {
        $completions = [];

        $completions = array_merge($completions, $this->getAttributeCompletions($model, $input));
        $completions = array_merge($completions, $this->getAccessorCompletions($model, $input));
        $completions = array_merge($completions, $this->getScopeCompletions(new ReflectionClass($model), $input));
        $completions = array_merge($completions, $this->getMethodCompletions($model, $input));
        $completions = array_merge($completions, $this->getQueryBuilderCompletions($input));

        return array_values(array_unique($completions));
    }

    /**
     * Get completions for a query builder of the given model.
     *
     * @param class-string<Model> $model
     * @return array<string>
     */
    private function getBuilderCompletions(string $model, string $input): array
    {
        $methods = array_merge(
            $this->publicMethods(EloquentBuilder::class),
            $this->publicMethods(QueryBuilder::class),
        );

        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            $methods = array_merge($methods, ['withTrashed', 'onlyTrashed', 'withoutTrashed']);
        }

        return array_values(array_unique(array_merge(
            $this->getScopeCompletions(new ReflectionClass($model), $input),
            $this->filterByPrefix($methods, $input),
        )));
    }

    /**
     * @param class-string $class
     * @return array<string>
     */
    private function publicMethods(string $class): array
    {
        $methods = [];

        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (! $method->isStatic() && ! str_starts_with($method->getName(), '__')) {
                $methods[] = $method->getName();
            }
        }

        return $methods;
    }

    /**
     * Get attribute completions from the model's attributes array.
     *
     * @param Model $model
     * @param string $input
     * @return array<string>
     */
    private function getAttributeCompletions(Model $model, string $input): array
    {
        $attributes = array_keys($model->getAttributes());

        return $this->filterByPrefix($attributes, $input);
    }

    /**
     * Get accessor completions from the model's accessor methods.
     *
     * @param Model $model
     * @param string $input
     * @return array<string>
     */
    private function getAccessorCompletions(Model $model, string $input): array
    {
        $reflection = new ReflectionClass($model);
        $accessors = [];

        foreach ($reflection->getMethods() as $method) {
            $methodName = $method->getName();

            if ($method->isPublic() && preg_match('/^get(\w+)Attribute$/', $methodName, $matches)) {
                $accessors[] = $this->snakeCase($matches[1]);
            } elseif ($this->returnsAttribute($method)) {
                $accessors[] = $this->snakeCase($methodName);
            }
        }

        return $this->filterByPrefix($accessors, $input);
    }

    /**
     * Get local scope completions, from #[Scope] methods and scopeXxx methods.
     *
     * @param ReflectionClass<object> $reflection
     * @return array<string>
     */
    private function getScopeCompletions(ReflectionClass $reflection, string $input): array
    {
        $scopes = [];

        foreach ($reflection->getMethods() as $method) {
            if ($method->isStatic()) {
                continue;
            }

            if ($method->getAttributes(Scope::class) !== []) {
                $scopes[] = $method->getName();
            } elseif (preg_match('/^scope(\w+)$/', $method->getName(), $matches)) {
                $scopes[] = lcfirst($matches[1]);
            }
        }

        return $this->filterByPrefix($scopes, $input);
    }

    private function returnsAttribute(ReflectionMethod $method): bool
    {
        $returnType = $method->getReturnType();

        return $returnType instanceof ReflectionNamedType
            && $returnType->getName() === Attribute::class
            && $method->getNumberOfRequiredParameters() === 0;
    }

    /**
     * Get method completions from the model.
     *
     * @param Model $model
     * @param string $input
     * @return array<string>
     */
    private function getMethodCompletions(Model $model, string $input): array
    {
        $reflection = new ReflectionClass($model);
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (! $method->isStatic() && ! $method->isConstructor() && ! str_starts_with($method->getName(), '__')) {
                $methods[] = $method->getName();
            }
        }

        return $this->filterByPrefix($methods, $input);
    }

    /**
     * Get query builder method completions for static calls.
     *
     * @param string $input
     * @return array<string>
     */
    private function getQueryBuilderCompletions(string $input): array
    {
        $methods = [
            'all',
            'create',
            'find',
            'findOrFail',
            'findMany',
            'findOrNew',
            'firstOrNew',
            'firstOrCreate',
            'firstOrFail',
            'firstWhere',
            'updateOrCreate',
            'insert',
            'insertOrIgnore',
            'insertGetId',
            'insertUsing',
            'update',
            'upsert',
            'delete',
            'destroy',
            'forceDelete',
            'restore',
            'where',
            'whereIn',
            'whereNotIn',
            'whereBetween',
            'whereNotBetween',
            'whereNull',
            'whereNotNull',
            'whereDate',
            'whereTime',
            'whereDay',
            'whereMonth',
            'whereYear',
            'whereColumn',
            'whereExists',
            'orderBy',
            'orderByDesc',
            'latest',
            'oldest',
            'inRandomOrder',
            'limit',
            'offset',
            'take',
            'skip',
            'count',
            'max',
            'min',
            'avg',
            'sum',
            'exists',
            'doesntExist',
            'pluck',
            'get',
            'first',
            'chunk',
            'each',
            'with',
            'withCount',
            'has',
            'whereHas',
            'doesntHave',
            'withTrashed',
            'onlyTrashed',
            'withoutTrashed',
        ];

        return $this->filterByPrefix($methods, $input);
    }

    /**
     * Filter an array of strings by prefix.
     *
     * @param array<string> $items
     * @param string $prefix
     * @return array<string>
     */
    private function filterByPrefix(array $items, string $prefix): array
    {
        if (empty($prefix)) {
            return $items;
        }

        return array_filter($items, function ($item) use ($prefix) {
            return self::startsWith($prefix, $item);
        });
    }

    /**
     * Convert a string to snake_case.
     *
     * @param string $value
     * @return string
     */
    private function snakeCase(string $value): string
    {
        $value = preg_replace('/([A-Z])/', '_$1', $value);
        $value = ltrim($value ?? '', '_');
        return strtolower($value);
    }
}
