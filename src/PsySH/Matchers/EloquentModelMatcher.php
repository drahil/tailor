<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH\Matchers;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Psy\TabCompletion\Matcher\AbstractContextAwareMatcher;
use ReflectionClass;
use ReflectionMethod;

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
        $operatorToken = null;

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
     * Get matches for instance method/property access.
     *
     * @param array<mixed> $tokens
     * @param string $input
     * @return array<string>
     */
    private function getInstanceMatches(array $tokens, string $input): array
    {
        $objectToken = array_pop($tokens);

        if (! is_array($objectToken)) {
            return [];
        }

        $objectName = str_replace('$', '', $objectToken[1]);

        try {
            $object = $this->getVariable($objectName);
        } catch (InvalidArgumentException $e) {
            return [];
        }

        if (! $object instanceof Model) {
            return [];
        }

        return $this->getModelCompletions($object, $input);
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

        return $this->getQueryBuilderCompletions($input);
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
        $completions = array_merge($completions, $this->getMethodCompletions($model, $input));

        return array_values(array_unique($completions));
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

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->getName();

            if (preg_match('/^get(\w+)Attribute$/', $methodName, $matches)) {
                $propertyName = $this->snakeCase($matches[1]);
                $accessors[] = $propertyName;
            }
        }

        return $this->filterByPrefix($accessors, $input);
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
