<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH\Matchers;

use Psy\TabCompletion\Matcher\ClassMethodsMatcher;

/**
 * A wrapper around ClassMethodsMatcher that skips Eloquent models.
 * This allows EloquentModelMatcher to handle Model classes exclusively.
 */
class FilteredClassMethodsMatcher extends ClassMethodsMatcher
{
    use FiltersEloquentModels;

    /**
     * Get matches, but skip if the class is an Eloquent Model.
     *
     * @param array<mixed> $tokens
     * @param array<mixed> $info
     * @return array<string>
     */
    public function getMatches(array $tokens, array $info = []): array
    {
        if ($this->isModelStaticAccess($tokens)) {
            return [];
        }

        return parent::getMatches($tokens, $info);
    }
}
