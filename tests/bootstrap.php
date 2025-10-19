<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

DG\BypassFinals::enable();

/* Define Laravel helper functions for tests */
if (! function_exists('app')) {
    function app(?string $abstract = null, array $parameters = []): null
    {
        if ($abstract === null) {
            return new class {
                public function version(): string
                {
                    return '11.0.0';
                }
            };
        }

        return null;
    }
}

if (! function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return sys_get_temp_dir() . '/tailor-test-storage/' . ltrim($path, '/');
    }
}
