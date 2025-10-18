<?php

declare(strict_types=1);

namespace drahil\Tailor\Support\Validation;

use RuntimeException;

/**
 * Exception thrown when validation fails.
 */
final class ValidationException extends RuntimeException
{
    public function __construct(
        public readonly ValidationResult $result
    ) {
        parent::__construct(
            'Validation failed: ' . $result->errorsAsString()
        );
    }
}
