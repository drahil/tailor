<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH\Matchers;

use Psy\TabCompletion\Matcher\ObjectMethodsMatcher;

/**
 * A wrapper around ObjectMethodsMatcher that skips Eloquent model instances.
 * This allows EloquentModelMatcher to handle Model instances exclusively.
 */
class FilteredObjectMethodsMatcher extends ObjectMethodsMatcher
{
    use FiltersEloquentModels;

    /**
     * Get matches, but skip if the object is an Eloquent Model instance.
     *
     * @param array<mixed> $tokens
     * @param array<mixed> $info
     * @return array<string>
     */
    public function getMatches(array $tokens, array $info = []): array
    {
        if ($this->isModelInstanceAccess($tokens)) {
            return [];
        }

        return parent::getMatches($tokens, $info);
    }
}
