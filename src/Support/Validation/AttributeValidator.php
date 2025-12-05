<?php

declare(strict_types=1);

namespace drahil\Tailor\Support\Validation;

use drahil\Tailor\Support\Validation\Attributes\MaxLength;
use drahil\Tailor\Support\Validation\Attributes\NotEmpty;
use drahil\Tailor\Support\Validation\Attributes\Pattern;
use ReflectionClass;
use ReflectionProperty;

/**
 * Validates object properties using validation attributes.
 *
 * This service provides attribute-based validation for value objects
 * and DTOs, making validation declarative and reusable.
 */
final class AttributeValidator
{
    /**
     * Validate an object using its property attributes.
     *
     * @param object $object The object to validate
     * @return ValidationResult The validation result
     */
    public static function validate(object $object): ValidationResult
    {
        $errors = [];
        $reflection = new ReflectionClass($object);

        foreach ($reflection->getProperties() as $property) {
            $propertyErrors = self::validateProperty($property, $object);
            $errors = array_merge($errors, $propertyErrors);
        }

        return new ValidationResult($errors);
    }

    /**
     * Validate a single property and its attributes.
     *
     * @param ReflectionProperty $property
     * @param object $object
     * @return array<string>
     */
    private static function validateProperty(ReflectionProperty $property, object $object): array
    {
        $errors = [];
        $value = $property->getValue($object);
        $propertyName = $property->getName();

        foreach ($property->getAttributes() as $attribute) {
            $instance = $attribute->newInstance();

            if ($instance instanceof NotEmpty || $instance instanceof Pattern) {
                if (! $instance->validate($value)) {
                    $errors[] = "{$propertyName}: {$instance->message}";
                }
            }

            if ($instance instanceof MaxLength) {
                if (! $instance->validate($value)) {
                    $errors[] = "{$propertyName}: {$instance->getFormattedMessage($value)}";
                }
            }
        }

        return $errors;
    }

    /**
     * Validate and throw exception if validation fails.
     *
     * @param object $object
     * @throws ValidationException
     */
    public static function validateOrFail(object $object): void
    {
        $result = self::validate($object);

        if ($result->hasErrors()) {
            throw new ValidationException($result);
        }
    }
}
