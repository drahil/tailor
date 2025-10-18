<?php

/**
 * PHPStan bootstrap file for stubbing Laravel helpers and facades.
 */

if (! function_exists('app')) {
    /**
     * @template T
     * @param class-string<T>|null $abstract
     * @return ($abstract is null ? mixed : T)
     */
    function app(?string $abstract = null): mixed
    {
        return null;
    }
}

if (! function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return '';
    }
}

if (! function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return '';
    }
}

if (! function_exists('config')) {
    /**
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    function config(?string $key = null, mixed $default = null): mixed
    {
        return $default;
    }
}
