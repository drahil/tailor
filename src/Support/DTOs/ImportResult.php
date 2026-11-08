<?php

declare(strict_types=1);

namespace drahil\Tailor\Support\DTOs;

final readonly class ImportResult
{
    private function __construct(
        private ?string $includeFile,
    ) {}

    public static function success(string $includeFile): self
    {
        return new self($includeFile);
    }

    public static function empty(): self
    {
        return new self(null);
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
