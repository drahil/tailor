<?php

declare(strict_types=1);

namespace drahil\Tailor\Support;

/**
 * Validates session-related data and formats.
 *
 * This service provides reusable validation logic for session names
 * and other session-related data that can be used across commands,
 * controllers, and other parts of the application.
 */
class SessionValidator
{
    /**
     * Regular expression pattern for valid session names.
     */
    private const NAME_PATTERN = '/^[a-zA-Z0-9_-]+$/';

    /**
     * Validate a session name format.
     *
     * Session names must contain only alphanumeric characters,
     * hyphens, and underscores.
     *
     * @param string $name The session name to validate
     * @return bool True if the name is valid, false otherwise
     */
    public function isValidName(string $name): bool
    {
        return preg_match(self::NAME_PATTERN, $name) === 1;
    }

    /**
     * Get the validation error message for session names.
     *
     * @return string The error message to display when validation fails
     */
    public function getNameValidationMessage(): string
    {
        return 'Invalid session name. Use only alphanumeric characters, hyphens, and underscores.';
    }

    /**
     * Validate that a session name is not empty.
     *
     * @param string|null $name The session name to check
     * @return bool True if the name is not empty, false otherwise
     */
    public function isNameProvided(?string $name): bool
    {
        return !empty($name);
    }

    /**
     * Get the error message for missing session name.
     *
     * @return string The error message to display when name is missing
     */
    public function getNameRequiredMessage(): string
    {
        return 'Name of the session must be provided.';
    }
}
