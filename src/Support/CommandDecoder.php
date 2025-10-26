<?php

declare(strict_types=1);

namespace drahil\Tailor\Support;

use Illuminate\Contracts\Container\Singleton;

/**
 * Decodes command code from storage format.
 *
 * Handles URL encoding and escape sequences used in history storage,
 * ensuring commands can be properly read and executed.
 */
#[Singleton]
class CommandDecoder
{
    /**
     * Decode command code from storage format.
     *
     * Handles URL encoding and escape sequences used in history storage.
     *
     * @param string $code The encoded command code
     * @return string The decoded command code
     */
    public function decode(string $code): string
    {
        if (str_contains($code, '\\0') || str_contains($code, '\\1')) {
            return stripcslashes($code);
        }

        $decoded = urldecode($code);
        return $decoded !== $code ? $decoded : $code;
    }
}
