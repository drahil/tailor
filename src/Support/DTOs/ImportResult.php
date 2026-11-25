<?php

declare(strict_types=1);

namespace drahil\Tailor\Support\DTOs;

final readonly class ImportResult
{
    /**
     * @param array<string, string> $aliases Alias => FQN of the auto-imported classes
     */
    private function __construct(
        private ?string $includeFile,
        private array $aliases = [],
    ) {}

    /**
     * @param array<string, string> $aliases
     */
    public static function success(string $includeFile, array $aliases = []): self
    {
        return new self($includeFile, $aliases);
    }

    public static function empty(): self
    {
        return new self(null);
    }

    /**
     * @return array<string, string>
     */
    public function getAliases(): array
    {
        return $this->aliases;
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
