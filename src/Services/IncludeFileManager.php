<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

use RuntimeException;

class IncludeFileManager
{
    /**
     * Create a temporary include file with the given use statements.
     *
     * @param array<string> $useStatements
     */
    public function createIncludeFile(array $useStatements): string
    {
        $content = "<?php\n\n" . implode("\n", $useStatements) . "\n";
        $tempFile = sys_get_temp_dir() . '/tailor_imports_' . uniqid() . '.php';

        if (@file_put_contents($tempFile, $content) === false) {
            throw new RuntimeException('Failed to create include file at: ' . $tempFile);
        }

        return $tempFile;
    }

    /**
     * Clean up a temporary include file.
     */
    public function cleanup(string $filePath): void
    {
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }
}
