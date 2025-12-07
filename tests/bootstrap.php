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

if (! function_exists('config')) {
    /**
     * @var array<string, mixed>
     */
    $testConfig = [];

    function config(string|array|null $key = null, mixed $default = null): mixed
    {
        global $testConfig;

        if ($key === null) {
            return $testConfig;
        }

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $testConfig[$k] = $v;
            }
            return null;
        }

        return $testConfig[$key] ?? $default;
    }
}
