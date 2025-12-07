<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH;

use drahil\Tailor\PsySH\Matchers\EloquentModelMatcher;
use drahil\Tailor\PsySH\Matchers\FilteredClassAttributesMatcher;
use drahil\Tailor\PsySH\Matchers\FilteredClassMethodsMatcher;
use drahil\Tailor\PsySH\Matchers\FilteredObjectAttributesMatcher;
use drahil\Tailor\PsySH\Matchers\FilteredObjectMethodsMatcher;
use Psy\TabCompletion\AutoCompleter;
use Psy\TabCompletion\Matcher\ClassNamesMatcher;
use Psy\TabCompletion\Matcher\ConstantsMatcher;
use Psy\TabCompletion\Matcher\FunctionsMatcher;
use Psy\TabCompletion\Matcher\KeywordsMatcher;
use Psy\TabCompletion\Matcher\VariablesMatcher;

/**
 * Custom autocompleter for Tailor that prioritizes Eloquent model completions.
 */
class TailorAutoCompleter extends AutoCompleter
{
    public function __construct()
    {
        parent::addMatcher(new EloquentModelMatcher());
        parent::addMatcher(new FilteredClassMethodsMatcher());
        parent::addMatcher(new FilteredClassAttributesMatcher());
        parent::addMatcher(new FilteredObjectMethodsMatcher());
        parent::addMatcher(new FilteredObjectAttributesMatcher());
        parent::addMatcher(new ClassNamesMatcher());
        parent::addMatcher(new VariablesMatcher());
        parent::addMatcher(new ConstantsMatcher());
        parent::addMatcher(new KeywordsMatcher());
        parent::addMatcher(new FunctionsMatcher());
    }

    /**
     * Override addMatcher to prevent Shell from adding default matchers.
     */
    public function addMatcher($matcher): void
    {
        /** Intentionally empty - prevents Shell from adding default matchers */
    }

    /**
     * Override processCallback to handle readline input format correctly.
     *
     * When readline passes "User::cre", we need to return "User::create", not just "create".
     *
     * @param array<string, mixed> $info
     * @return array<string>
     */
    public function processCallback(string $input, int $index, array $info = []): array
    {
        $result = parent::processCallback($input, $index, $info);

        if (empty($result) || $result === ['']) {
            return $result;
        }

        if (str_contains($input, '::')) {
            $parts = explode('::', $input);
            $prefix = $parts[0] . '::';
            $partial = $parts[1] ?? '';

            $result = array_map(function ($match) use ($prefix, $partial) {
                if (! str_starts_with($match, $prefix) && str_starts_with($match, $partial)) {
                    return $prefix . $match;
                }
                return $match;
            }, $result);
        } elseif (str_contains($input, '->')) {
            $parts = explode('->', $input);
            $prefix = $parts[0] . '->';
            $partial = $parts[1] ?? '';

            $result = array_map(function ($match) use ($prefix, $partial) {
                if (! str_starts_with($match, $prefix) && str_starts_with($match, $partial)) {
                    return $prefix . $match;
                }
                return $match;
            }, $result);
        }

        return $result;
    }
}
