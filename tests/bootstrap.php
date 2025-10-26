<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

DG\BypassFinals::enable();

/* Define Laravel helper functions for tests */
if (! function_exists('app')) {
    function app(?string $abstract = null, array $parameters = []): mixed
    {
        if ($abstract === null) {
            return new class {
                public function version(): string
                {
                    return '11.0.0';
                }

                public function basePath(string $path = ''): string
                {
                    return sys_get_temp_dir() . '/tailor-test-project/' . ltrim($path, '/');
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

/* Mock Storage facade for tests */
if (! class_exists('Illuminate\Support\Facades\Storage')) {
    class_alias('StorageFacadeMock', 'Illuminate\Support\Facades\Storage');
}

class StorageFacadeMock
{
    public static function disk(string $disk): self
    {
        return new self();
    }

    public function path(string $path = ''): string
    {
        return sys_get_temp_dir() . '/tailor-test-storage/' . ltrim($path, '/');
    }
}
