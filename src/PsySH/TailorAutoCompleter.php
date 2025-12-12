<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH;

use drahil\Tailor\PsySH\Matchers\EloquentModelMatcher;
use drahil\Tailor\PsySH\Matchers\FilteredClassAttributesMatcher;
use drahil\Tailor\PsySH\Matchers\FilteredClassMethodsMatcher;
use drahil\Tailor\PsySH\Matchers\FilteredObjectAttributesMatcher;
use drahil\Tailor\PsySH\Matchers\FilteredObjectMethodsMatcher;
use Psy\Context;
use Psy\ContextAware;
use Psy\TabCompletion\AutoCompleter;
use Psy\TabCompletion\Matcher\AbstractMatcher;
use Psy\TabCompletion\Matcher\ClassNamesMatcher;
use Psy\TabCompletion\Matcher\ConstantsMatcher;
use Psy\TabCompletion\Matcher\FunctionsMatcher;
use Psy\TabCompletion\Matcher\KeywordsMatcher;
use Psy\TabCompletion\Matcher\VariablesMatcher;

/**
 * Custom autocompleter for Tailor that prioritizes Eloquent model completions.
 */
class TailorAutoCompleter extends AutoCompleter implements ContextAware
{
    /** @var AbstractMatcher[] */
    private array $customMatchers = [];

    /** @var bool */
    private bool $matchersInitialized = false;

    public function __construct()
    {
        /** Store matchers but don't add them yet - wait for context to be set */
        $this->customMatchers = [
            new EloquentModelMatcher(),
            new FilteredClassMethodsMatcher(),
            new FilteredClassAttributesMatcher(),
            new FilteredObjectMethodsMatcher(),
            new FilteredObjectAttributesMatcher(),
            new ClassNamesMatcher(),
            new VariablesMatcher(),
            new ConstantsMatcher(),
            new KeywordsMatcher(),
            new FunctionsMatcher(),
        ];
    }

    /**
     * Set the Context for all matchers and initialize them.
     */
    public function setContext(Context $context): void
    {
        if ($this->matchersInitialized) {
            return;
        }

        /** Set context on matchers and add them to the parent autocompleter */
        foreach ($this->customMatchers as $matcher) {
            if ($matcher instanceof ContextAware) {
                $matcher->setContext($context);
            }
            parent::addMatcher($matcher);
        }

        $this->matchersInitialized = true;
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
