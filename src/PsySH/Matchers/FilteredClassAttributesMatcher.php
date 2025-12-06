<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH\Matchers;

use Psy\TabCompletion\Matcher\ClassAttributesMatcher;

/**
 * A wrapper around ClassAttributesMatcher that skips Eloquent models.
 * This allows EloquentModelMatcher to handle Model classes exclusively.
 */
class FilteredClassAttributesMatcher extends ClassAttributesMatcher
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
