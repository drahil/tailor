<?php

declare(strict_types=1);

namespace drahil\Tailor\Support\DTOs;

final readonly class ImportResult
{
    private function __construct(
        private bool $successful,
        private ?string $includeFile,
    ) {}

    public static function success(string $includeFile): self
    {
        return new self(true, $includeFile);
    }

    public static function failed(): self
    {
        return new self(false, null);
    }

    public static function empty(): self
    {
        return new self(true, null);
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function hasIncludeFile(): bool
    {
        return $this->includeFile !== null;
    }

    public function getIncludeFile(): ?string
    {
        return $this->includeFile;
    }
}
