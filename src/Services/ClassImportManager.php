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
     * @param array<string, string> $classes Map of FQN => file path
     * @return array<string> Array of class_alias statements to execute
     */
    public function generateUseStatements(array $classes): array
    {
        $shortNameMap = $this->groupByShortName($classes);
        $aliasStatements = [];

        foreach ($shortNameMap as $shortName => $fqnList) {
            if (count($fqnList) === 1) {
                $fqn = $fqnList[0];
                $aliasStatements[] = "class_alias('{$fqn}', '{$shortName}');";
            } else {
                foreach ($fqnList as $fqn) {
                    $alias = $this->generateAlias($fqn);
                    $aliasStatements[] = "class_alias('{$fqn}', '{$alias}');";
                }
            }
        }

        return $aliasStatements;
    }

    /**
     * Group fully qualified class names by their short name.
     *
     * @param array<string, string> $classes
     * @return array<string, array<string>>
     */
    private function groupByShortName(array $classes): array
    {
        $grouped = [];

        foreach (array_keys($classes) as $fqn) {
            $shortName = $this->getShortName($fqn);
            $grouped[$shortName][] = $fqn;
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
