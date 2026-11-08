<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

class ClassImportManager
{
    /**
     * Generate class_alias statements from discovered classes with conflict resolution.
     *
     * Uses prefix strategy: if class names conflict, prefix with namespace segment.
     * Examples:
     *   - App\Models\User (unique) -> class_alias('App\Models\User', 'User')
     *   - App\Models\User + App\Admin\User -> class_alias for ModelsUser and AdminUser
     *
     * Aliases that are reserved or clash with each other are skipped.
     *
     * @param array<string, string> $classes Map of FQN => file path
     * @param array<int, string> $reservedNames Names that are already taken
     * @return array{statements: array<int, string>, skipped: array<string, string>} Skipped is FQN => alias
     */
    public function generateAliases(array $classes, array $reservedNames = []): array
    {
        $aliases = $this->resolveAliases($classes);
        $reserved = array_flip(array_map('strtolower', $reservedNames));
        $aliasCounts = array_count_values(array_map('strtolower', $aliases));

        $statements = [];
        $skipped = [];

        foreach ($aliases as $fqn => $alias) {
            $key = strtolower($alias);

            if (isset($reserved[$key]) || $aliasCounts[$key] > 1) {
                $skipped[$fqn] = $alias;
                continue;
            }

            $statements[] = "try { class_alias('{$fqn}', '{$alias}'); } catch (\\Throwable) {}";
        }

        return ['statements' => $statements, 'skipped' => $skipped];
    }

    /**
     * @param array<string, string> $classes
     * @return array<string, string> Map of FQN => alias
     */
    private function resolveAliases(array $classes): array
    {
        $aliases = [];

        foreach ($this->groupByShortName($classes) as $fqnList) {
            foreach ($fqnList as $fqn) {
                $aliases[$fqn] = count($fqnList) === 1
                    ? $this->getShortName($fqn)
                    : $this->generateAlias($fqn);
            }
        }

        return $aliases;
    }

    /**
     * Group fully qualified class names by their short name, ignoring case.
     *
     * @param array<string, string> $classes
     * @return array<string, array<string>>
     */
    private function groupByShortName(array $classes): array
    {
        $grouped = [];

        foreach (array_keys($classes) as $fqn) {
            $grouped[strtolower($this->getShortName($fqn))][] = $fqn;
        }

        return $grouped;
    }

    /**
     * Get short name from fully qualified class name.
     */
    private function getShortName(string $fqn): string
    {
        $parts = explode('\\', $fqn);

        return end($parts);
    }

    /**
     * Generate an alias for a conflicting class using namespace prefix.
     *
     * Examples:
     *   - App\Models\User -> ModelsUser
     *   - App\Http\Controllers\UserController -> ControllersUserController
     *   - App\Admin\User -> AdminUser
     */
    private function generateAlias(string $fqn): string
    {
        $parts = explode('\\', ltrim($fqn, '\\'));
        $className = array_pop($parts);

        $namespaceParts = array_filter($parts, function ($part) {
            return $part !== 'App';
        });

        if (empty($namespaceParts)) {
            return $className;
        }

        return implode('', $namespaceParts) . $className;
    }
}
