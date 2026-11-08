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
use Psy\TabCompletion\Matcher\ClassAttributesMatcher;
use Psy\TabCompletion\Matcher\ClassMethodsMatcher;
use Psy\TabCompletion\Matcher\ClassNamesMatcher;
use Psy\TabCompletion\Matcher\ConstantsMatcher;
use Psy\TabCompletion\Matcher\FunctionsMatcher;
use Psy\TabCompletion\Matcher\KeywordsMatcher;
use Psy\TabCompletion\Matcher\ObjectAttributesMatcher;
use Psy\TabCompletion\Matcher\ObjectMethodsMatcher;
use Psy\TabCompletion\Matcher\VariablesMatcher;

/**
 * Custom autocompleter for Tailor that prioritizes Eloquent model completions.
 */
class TailorAutoCompleter extends AutoCompleter implements ContextAware
{
    /** @var array<class-string<AbstractMatcher>> */
    private const REPLACED_MATCHERS = [
        ClassMethodsMatcher::class,
        ClassAttributesMatcher::class,
        ObjectMethodsMatcher::class,
        ObjectAttributesMatcher::class,
        ClassNamesMatcher::class,
        VariablesMatcher::class,
        ConstantsMatcher::class,
        KeywordsMatcher::class,
        FunctionsMatcher::class,
    ];

    /** @var AbstractMatcher[] */
    private array $customMatchers = [];

    /** @var bool */
    private bool $matchersInitialized = false;

    /** @var array<int, string> */
    private array $commandNames = [];

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
     * Skip defaults that Tailor replaces with its own matchers.
     */
    public function addMatcher($matcher): void
    {
        if (in_array(get_class($matcher), self::REPLACED_MATCHERS, true)) {
            return;
        }

        parent::addMatcher($matcher);
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
        $commandMatches = $this->matchCommandNames($input, $info);
        if ($commandMatches !== null) {
            return $this->withoutTrailingSpace($commandMatches);
        }

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

        return $this->withoutTrailingSpace($result);
    }

    /**
     * @param array<int, string> $commandNames
     */
    public function setCommandNames(array $commandNames): void
    {
        $this->commandNames = $commandNames;
    }

    /**
     * Complete namespaced command names, which PsySH splits at the colon.
     *
     * @param array<string, mixed> $info
     * @return array<int, string>|null Null when the input is not a namespaced command name
     */
    private function matchCommandNames(string $input, array $info): ?array
    {
        $line = (string) ($info['line_buffer'] ?? $input);
        if (isset($info['end']) && is_int($info['end'])) {
            $line = substr($line, 0, $info['end']);
        }

        if (! preg_match('/^\s*([a-z][\w-]*:[\w-]*)$/i', $line, $matches)) {
            return null;
        }

        $matching = array_values(array_filter(
            $this->commandNames,
            fn (string $name) => str_starts_with($name, strtolower($matches[1]))
        ));

        return $matching === [] ? null : $matching;
    }

    /**
     * Stop readline from appending a space after a single completion.
     *
     * GNU readline honours completion_append_character. Libedit ignores it, but
     * only appends the space when there is exactly one match, so the match is doubled.
     *
     * @param array<int, string> $matches
     * @return array<int, string>
     */
    private function withoutTrailingSpace(array $matches): array
    {
        if (function_exists('readline_info')) {
            readline_info('completion_append_character', '');
        }

        $unique = array_values(array_unique($matches));

        return count($unique) === 1 && $unique[0] !== '' ? [$unique[0], $unique[0]] : $unique;
    }
}
