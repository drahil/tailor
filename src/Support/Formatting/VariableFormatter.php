<?php

declare(strict_types=1);

namespace drahil\Tailor\Support\Formatting;

/**
 * Formats variable values for console output.
 *
 * Provides type-aware formatting with color-coded output
 * for different PHP types.
 */
final class VariableFormatter
{
    /**
     * Format a variable value for console display.
     *
     * Handles different types with appropriate formatting:
     * - null: gray "null"
     * - bool: green "true" or red "false"
     * - string: quoted, escaped, truncated if > 80 chars
     * - numeric: plain string representation
     * - array: magenta "array(count)"
     * - object: magenta class name
     * - other: gray type name
     *
     * @param mixed $value The value to format
     * @return string Formatted string with console color tags
     */
    public static function format(mixed $value): string
    {
        if (is_null($value)) {
            return '<fg=gray>null</>';
        }

        if (is_bool($value)) {
            return $value ? '<fg=green>true</>' : '<fg=red>false</>';
        }

        if (is_string($value)) {
            $escaped = addslashes($value);
            if (strlen($escaped) > 80) {
                $escaped = substr($escaped, 0, 77) . '...';
            }
            return "\"{$escaped}\"";
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            $count = count($value);
            return "<fg=magenta>array({$count})</>";
        }

        if (is_object($value)) {
            return '<fg=magenta>' . get_class($value) . '</>';
        }

        return '<fg=gray>' . gettype($value) . '</>';
    }
}
